<?php

namespace Tests\Feature\SyncRuns;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Filament\Resources\GroceryAccounts\Pages\ListGroceryAccounts;
use App\Filament\Resources\SyncRuns\Pages\ListSyncRuns;
use App\Filament\Resources\SyncRuns\Pages\ViewSyncRun;
use App\Jobs\RaiffeisenLoginJob;
use App\Jobs\SyncGroceryAccountJob;
use App\Models\GroceryAccount;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Raiffeisen\Data\AccountBalance;
use App\Services\Raiffeisen\Data\LoginResult;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Services\Raiffeisen\RaiffeisenException;
use App\Support\RaiffeisenImportSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class SyncRunReportTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_a_failed_bank_login_is_reported_with_a_link(): void
    {
        $user = User::factory()->create();
        $run = SyncRun::open($user, SyncKind::BankLogin, summary: 'Signing in to Raiffeisen');
        $sessionId = RaiffeisenImportSession::start($user->id);
        RaiffeisenImportSession::setPassword($sessionId, 'pw');

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('login')->once();
        $client->shouldReceive('loginFont')->once()
            ->andThrow(new RaiffeisenException('Login failed - check your username and password.'));

        (new RaiffeisenLoginJob($sessionId, 'testuser', $run->id))->handle($client);

        $run->refresh();
        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertSame('Login failed - check your username and password.', $run->message);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Bank login failed', $notification->data['title']);
        $this->assertSame('danger', $notification->data['status']);
        $this->assertSame('View report', $notification->data['actions'][0]['label']);
        $this->assertStringContainsString('/admin/sync-reports/'.$run->id, $notification->data['actions'][0]['url']);
        // The bell badge counts unread rows with a JSON operator. That query
        // fails if notifications.data is text rather than jsonb.
        $this->assertSame(1, $user->unreadNotifications()->where('data->format', 'filament')->count());
    }

    public function test_a_successful_bank_login_leaves_no_report(): void
    {
        $user = User::factory()->create();
        $run = SyncRun::open($user, SyncKind::BankLogin, summary: 'Signing in to Raiffeisen');
        $sessionId = RaiffeisenImportSession::start($user->id);
        RaiffeisenImportSession::setPassword($sessionId, 'pw');

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('login')->once();
        $client->shouldReceive('loginFont')->once()
            ->andReturn(new LoginResult('ticket', 'reqtok', false, 1, 2));
        $client->shouldReceive('allAccountBalance')->once()->andReturn([
            new AccountBalance('12345', 'Test account', 'RSD', '941', '33', 1000, 900, null, null),
        ]);
        $client->shouldReceive('exportCookies')->once()->andReturn(['cookie' => 'value']);

        (new RaiffeisenLoginJob($sessionId, 'testuser', $run->id))->handle($client);

        $this->assertDatabaseMissing('sync_runs', ['id' => $run->id]);
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame('ready', RaiffeisenImportSession::getState($sessionId)['status']);
    }

    public function test_a_second_grocery_sync_is_reported_without_dispatching_again(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $account = GroceryAccount::factory()->withValidToken()->create();

        Livewire::test(ListGroceryAccounts::class)->callTableAction('sync', $account);
        Livewire::test(ListGroceryAccounts::class)->callTableAction('sync', $account);

        $this->assertSame(1, SyncRun::query()->where('status', SyncRunStatus::Running)->count());
        $failed = SyncRun::query()->where('status', SyncRunStatus::Failed)->first();
        $this->assertNotNull($failed);
        $this->assertSame('A sync for this account is already running.', $failed->message);
        Queue::assertPushed(SyncGroceryAccountJob::class, 1);

        $notification = $admin->notifications()->first();
        $this->assertSame('danger', $notification->data['status']);
        $this->assertStringContainsString('/admin/sync-reports/'.$failed->id, $notification->data['actions'][0]['url']);
    }

    public function test_bank_reports_stay_private_and_admins_can_read_every_grocery_run(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $myImport = SyncRun::factory()->for($user)->create([
            'kind' => SyncKind::BankImport,
            'summary' => 'My import',
        ]);
        $theirImport = SyncRun::factory()->for($admin)->create([
            'kind' => SyncKind::BankImport,
            'summary' => 'Admin import',
        ]);
        $grocery = SyncRun::factory()->for($otherAdmin)->create([
            'kind' => SyncKind::GrocerySync,
            'summary' => 'Household grocery sync',
        ]);

        $this->assertTrue($user->can('view', $myImport));
        $this->assertFalse($user->can('view', $theirImport));
        $this->assertFalse($user->can('view', $grocery));
        $this->assertTrue($admin->can('view', $grocery));
        $this->assertFalse($admin->can('view', $myImport));

        $this->actingAs($user);
        Livewire::test(ListSyncRuns::class)
            ->assertCanSeeTableRecords([$myImport])
            ->assertCanNotSeeTableRecords([$theirImport, $grocery]);

        $this->actingAs($admin);
        Livewire::test(ListSyncRuns::class)
            ->assertCanSeeTableRecords([$grocery, $theirImport])
            ->assertCanNotSeeTableRecords([$myImport]);

        Livewire::test(ViewSyncRun::class, ['record' => $grocery->getKey()])
            ->assertOk()
            ->assertSee('Household grocery sync');

        $this->actingAs($user);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ViewSyncRun::class, ['record' => $theirImport->getKey()]);
    }

    public function test_the_report_page_shows_why_an_account_failed(): void
    {
        $user = User::factory()->create();
        $run = SyncRun::factory()->for($user)->create([
            'kind' => SyncKind::BankImport,
            'status' => SyncRunStatus::Partial,
            'summary' => '1 account imported, 1 failed',
            'message' => 'turnover fetch blew up',
            'details' => [
                'accounts' => [[
                    'account_number' => '11111',
                    'description' => 'Checking',
                    'inserted' => 0,
                    'failed' => true,
                    'message' => 'turnover fetch blew up',
                ]],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(ViewSyncRun::class, ['record' => $run->getKey()])
            ->assertOk()
            ->assertSee('turnover fetch blew up')
            ->assertSee('Checking')
            ->assertSee('11111');
    }
}
