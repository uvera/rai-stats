<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ImportTransactions;
use App\Jobs\RaiffeisenLoginJob;
use App\Models\Account;
use App\Models\ImportCoverage;
use App\Models\User;
use App\Support\RaiffeisenImportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ImportTransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_submitting_credentials_dispatches_the_login_job_and_clears_the_password(): void
    {
        Queue::fake();
        $user = $this->actingUser();

        $component = Livewire::test(ImportTransactions::class)
            ->set('username', 'raiuser')
            ->set('password', 'super-secret')
            ->call('submitCredentials');

        $component->assertSet('step', 'waiting');
        $component->assertSet('password', '');

        Queue::assertPushed(RaiffeisenLoginJob::class);

        $this->assertSame('raiuser', $user->fresh()->raiffeisen_username);
    }

    public function test_poll_transitions_to_select_and_creates_accounts_on_ready(): void
    {
        $this->actingUser();

        $component = Livewire::test(ImportTransactions::class)
            ->set('importSessionId', $sessionId = RaiffeisenImportSession::start(1));

        RaiffeisenImportSession::setState($sessionId, [
            'status' => 'ready',
            'cookies' => ['foo' => 'bar'],
            // Plain arrays, matching what RaiffeisenLoginJob actually stores
            // (Laravel's cache stores refuse to unserialize arbitrary
            // objects by default - see RaiffeisenLoginJob for details).
            'accounts' => [
                [
                    'number' => '11111',
                    'description' => 'RSD account',
                    'currency_code' => 'RSD',
                    'currency_code_numeric' => '941',
                    'product_core_id' => '33',
                ],
            ],
        ]);

        $component->call('poll');

        $component->assertSet('step', 'select');
        $this->assertDatabaseHas('accounts', ['number' => '11111', 'currency_code' => 'RSD']);
    }

    public function test_poll_transitions_to_error_on_failure(): void
    {
        $this->actingUser();

        $component = Livewire::test(ImportTransactions::class)
            ->set('importSessionId', $sessionId = RaiffeisenImportSession::start(1));

        RaiffeisenImportSession::setState($sessionId, [
            'status' => 'failed',
            'message' => 'bad credentials',
        ]);

        $component->call('poll');

        $component->assertSet('step', 'error');
        $component->assertSet('errorMessage', 'bad credentials');
    }

    public function test_add_range_trims_against_existing_coverage(): void
    {
        $user = $this->actingUser();

        $account = Account::create([
            'user_id' => $user->id,
            'number' => '22222',
            'description' => 'Test',
            'currency_code' => 'RSD',
            'currency_code_numeric' => '941',
            'product_core_id' => '33',
        ]);

        ImportCoverage::create([
            'account_id' => $account->id,
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-15',
        ]);

        Livewire::test(ImportTransactions::class)
            ->set('selectedAccountNumber', '22222')
            ->set('fromDate', '2026-01-01')
            ->set('toDate', '2026-01-31')
            ->call('addRange')
            ->assertSet('queuedRanges', [
                ['account_number' => '22222', 'from' => '2026-01-16', 'to' => '2026-01-31'],
            ])
            ->assertSet('rangeNotice', 'Part of that range is already imported - only the missing part was added.');
    }

    public function test_add_range_does_nothing_when_fully_covered(): void
    {
        $user = $this->actingUser();

        $account = Account::create([
            'user_id' => $user->id,
            'number' => '33333',
            'description' => 'Test',
            'currency_code' => 'RSD',
            'currency_code_numeric' => '941',
            'product_core_id' => '33',
        ]);

        ImportCoverage::create([
            'account_id' => $account->id,
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
        ]);

        Livewire::test(ImportTransactions::class)
            ->set('selectedAccountNumber', '33333')
            ->set('fromDate', '2026-01-10')
            ->set('toDate', '2026-01-20')
            ->call('addRange')
            ->assertSet('queuedRanges', []);
    }

    public function test_remove_range(): void
    {
        $this->actingUser();

        Livewire::test(ImportTransactions::class)
            ->set('queuedRanges', [
                ['account_number' => 'a', 'from' => '2026-01-01', 'to' => '2026-01-05'],
                ['account_number' => 'b', 'from' => '2026-02-01', 'to' => '2026-02-05'],
            ])
            ->call('removeRange', 0)
            ->assertSet('queuedRanges', [
                ['account_number' => 'b', 'from' => '2026-02-01', 'to' => '2026-02-05'],
            ]);
    }
}
