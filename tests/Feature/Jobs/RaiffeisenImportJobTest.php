<?php

namespace Tests\Feature\Jobs;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Enums\TransactionType;
use App\Jobs\RaiffeisenImportJob;
use App\Models\Account;
use App\Models\SyncRun;
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
    private function runJob(string $sessionId, int $userId, array $ranges, RaiffeisenClient $client): SyncRun
    {
        $run = SyncRun::open(User::findOrFail($userId), SyncKind::BankImport, summary: 'Importing transactions');

        $job = Mockery::mock(RaiffeisenImportJob::class, [$sessionId, $userId, $ranges, $run->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('client')->andReturn($client);

        $job->handle();

        return $run->fresh();
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

        $run = $this->runJob($sessionId, $user->id, [
            ['account_number' => '11111', 'from' => '2026-01-01', 'to' => '2026-03-31'],
        ], $client);

        $state = RaiffeisenImportSession::getState($sessionId);
        $this->assertSame('done', $state['status']);
        $this->assertSame(2, $state['import_results'][0]['inserted']);
        $this->assertFalse($state['import_results'][0]['failed']);
        $this->assertSame(SyncRunStatus::Succeeded, $run->status);
        $this->assertSame('Imported 2 transactions', $run->summary);
        $this->assertSame('success', $user->notifications()->first()->data['status']);
        $this->assertStringContainsString('/admin/sync-reports/'.$run->id, $user->notifications()->first()->data['actions'][0]['url']);

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

        $run = $this->runJob($sessionId, $user->id, [
            ['account_number' => '11111', 'from' => '2026-01-01', 'to' => '2026-01-31'],
            ['account_number' => '22222', 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ], $client);

        $results = collect(RaiffeisenImportSession::getState($sessionId)['import_results'])->keyBy('account_number');
        $this->assertTrue($results['11111']['failed']);
        $this->assertSame('turnover fetch blew up', $results['11111']['message']);
        $this->assertFalse($results['22222']['failed']);
        $this->assertSame(1, $results['22222']['inserted']);
        $this->assertSame('done', RaiffeisenImportSession::getState($sessionId)['status']);
        $this->assertSame(SyncRunStatus::Partial, $run->status);
        $this->assertSame('turnover fetch blew up', $run->message);
        $this->assertSame('warning', $user->notifications()->first()->data['status']);

        Log::shouldHaveReceived('warning')->atLeast()->once();
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
        $this->assertSame('This account could not be imported.', $result['message']);
        $this->assertSame(0, Transaction::count());
    }

    public function test_an_unexpected_account_error_hides_the_exception_text(): void
    {
        $user = User::factory()->create();
        $this->account($user);

        $sessionId = RaiffeisenImportSession::start($user->id);
        RaiffeisenImportSession::setState($sessionId, ['cookies' => ['a' => 'b']]);

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('transactionalAccountTurnover')
            ->andThrow(new \RuntimeException('upstream body token=secret-value'));

        $run = $this->runJob($sessionId, $user->id, [
            ['account_number' => '11111', 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ], $client);

        $this->assertStringNotContainsString('secret-value', (string) $run->message);
        $this->assertStringContainsString('unexpectedly', (string) $run->message);
        $this->assertSame(SyncRunStatus::Failed, $run->status);
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

        $run = SyncRun::open($user, SyncKind::BankImport, summary: 'Importing transactions');
        (new RaiffeisenImportJob($sessionId, $user->id, [], $run->id))->failed(new \RuntimeException('worker died'));

        $state = RaiffeisenImportSession::getState($sessionId);
        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('unexpectedly', $state['message']);
        $this->assertSame(SyncRunStatus::Failed, $run->fresh()->status);
        $this->assertSame('danger', $user->notifications()->first()->data['status']);

        Log::shouldHaveReceived('error')->once();
    }
}
