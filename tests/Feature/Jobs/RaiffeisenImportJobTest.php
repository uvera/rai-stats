<?php

namespace Tests\Feature\Jobs;

use App\Enums\TransactionType;
use App\Jobs\RaiffeisenImportJob;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction as TransactionDto;
use App\Services\Raiffeisen\Data\TransactionType as RaiffeisenTransactionType;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Services\Raiffeisen\RaiffeisenException;
use App\Support\RaiffeisenImportSession;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class RaiffeisenImportJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private function account(User $user, string $number = '11111'): Account
    {
        return Account::create([
            'user_id' => $user->id,
            'number' => $number,
            'description' => "Account {$number}",
            'currency_code' => 'RSD',
            'currency_code_numeric' => '941',
            'product_core_id' => '33',
        ]);
    }

    private function dto(string $bankId): TransactionDto
    {
        return new TransactionDto(
            currencyCodeNumeric: '941',
            currencyCode: 'RSD',
            date: new DateTimeImmutable('2026-03-15 10:00:00'),
            place: 'Shop',
            reference: '',
            amountCents: -1234,
            description: '',
            bankTransactionId: $bankId,
            type: RaiffeisenTransactionType::Pos,
        );
    }

    /**
     * @param  array<int, array{account_number: string, from: string, to: string}>  $ranges
     */
    private function runJob(string $sessionId, int $userId, array $ranges, RaiffeisenClient $client): void
    {
        $job = Mockery::mock(RaiffeisenImportJob::class, [$sessionId, $userId, $ranges])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('client')->andReturn($client);

        $job->handle();
    }

    public function test_imports_turnover_and_reserved_then_marks_the_session_done(): void
    {
        $user = User::factory()->create();
        $this->account($user);

        $sessionId = RaiffeisenImportSession::start($user->id);
        RaiffeisenImportSession::setState($sessionId, ['cookies' => ['a' => 'b']]);

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('transactionalAccountTurnover')->once()
            ->andReturn([$this->dto('b-1'), $this->dto('b-2')]);
        // Reserved funds fetched exactly once for the account, not per range.
        $client->shouldReceive('transactionalAccountReservedFunds')->once()
            ->andReturn([new ReservedTransaction(new DateTimeImmutable('2026-03-16'), 'Hold', -500, 'RSD', '941')]);

        $this->runJob($sessionId, $user->id, [
            ['account_number' => '11111', 'from' => '2026-01-01', 'to' => '2026-03-31'],
        ], $client);

        $state = RaiffeisenImportSession::getState($sessionId);
        $this->assertSame('done', $state['status']);
        $this->assertSame(2, $state['import_results'][0]['inserted']);
        $this->assertFalse($state['import_results'][0]['failed']);

        $this->assertSame(2, Transaction::query()->where('type', TransactionType::Pos->value)->count());
        $this->assertSame(1, Transaction::query()->where('type', TransactionType::Reserved->value)->count());
    }

    public function test_a_failing_account_is_recorded_and_the_run_continues(): void
    {
        $user = User::factory()->create();
        $this->account($user, '11111');
        $this->account($user, '22222');

        $sessionId = RaiffeisenImportSession::start($user->id);
        RaiffeisenImportSession::setState($sessionId, ['cookies' => ['a' => 'b']]);

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('transactionalAccountTurnover')
            ->andReturnUsing(function (string $productCoreId, string $number) {
                if ($number === '11111') {
                    throw new RaiffeisenException('turnover fetch blew up');
                }

                return [$this->dto('ok-1')];
            });
        $client->shouldReceive('transactionalAccountReservedFunds')->andReturn([]);

        Log::spy();

        $this->runJob($sessionId, $user->id, [
            ['account_number' => '11111', 'from' => '2026-01-01', 'to' => '2026-01-31'],
            ['account_number' => '22222', 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ], $client);

        $results = collect(RaiffeisenImportSession::getState($sessionId)['import_results'])->keyBy('account_number');
        $this->assertTrue($results['11111']['failed']);
        $this->assertFalse($results['22222']['failed']);
        $this->assertSame(1, $results['22222']['inserted']);
        $this->assertSame('done', RaiffeisenImportSession::getState($sessionId)['status']);

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_an_account_belonging_to_another_user_is_refused(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $this->account($someoneElse, '99999');

        $sessionId = RaiffeisenImportSession::start($me->id);
        RaiffeisenImportSession::setState($sessionId, ['cookies' => ['a' => 'b']]);

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldNotReceive('transactionalAccountTurnover');

        $this->runJob($sessionId, $me->id, [
            ['account_number' => '99999', 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ], $client);

        $result = RaiffeisenImportSession::getState($sessionId)['import_results'][0];
        $this->assertTrue($result['failed']);
        $this->assertSame(0, Transaction::count());
    }

    public function test_missing_cookies_fail_the_session(): void
    {
        $user = User::factory()->create();
        $sessionId = RaiffeisenImportSession::start($user->id);

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldNotReceive('transactionalAccountTurnover');

        $this->runJob($sessionId, $user->id, [
            ['account_number' => '11111', 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ], $client);

        $this->assertSame('failed', RaiffeisenImportSession::getState($sessionId)['status']);
    }

    public function test_the_failed_hook_records_a_generic_failure(): void
    {
        $user = User::factory()->create();
        $sessionId = RaiffeisenImportSession::start($user->id);

        Log::spy();

        (new RaiffeisenImportJob($sessionId, $user->id, []))->failed(new \RuntimeException('worker died'));

        $state = RaiffeisenImportSession::getState($sessionId);
        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('unexpectedly', $state['message']);

        Log::shouldHaveReceived('error')->once();
    }
}
