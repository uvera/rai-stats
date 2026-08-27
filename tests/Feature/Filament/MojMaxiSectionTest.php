<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\MojMaxiStats;
use App\Filament\Resources\MaxiAccounts\Pages\CreateMaxiAccount;
use App\Filament\Resources\MaxiAccounts\Pages\ListMaxiAccounts;
use App\Filament\Resources\MaxiReceipts\Pages\ListMaxiReceipts;
use App\Filament\Resources\MaxiReceipts\Pages\ViewMaxiReceipt;
use App\Models\MaxiAccount;
use App\Models\MaxiReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MojMaxiSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_can_view_the_moj_maxi_pages(): void
    {
        $this->actingAs(User::factory()->create());
        MaxiAccount::factory()->create();
        MaxiReceipt::factory()->create();

        Livewire::test(ListMaxiAccounts::class)->assertOk();
        Livewire::test(ListMaxiReceipts::class)->assertOk();
        Livewire::test(MojMaxiStats::class)->assertOk();
    }

    public function test_non_admin_cannot_create_a_maxi_account(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateMaxiAccount::class)->assertForbidden();
    }

    public function test_admin_can_create_a_maxi_account_and_a_device_uuid_is_generated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateMaxiAccount::class)
            ->set('data.label', 'Household Maxi')
            ->set('data.email', 'household@example.com')
            ->call('create')
            ->assertHasNoFormErrors();

        $account = MaxiAccount::firstWhere('email', 'household@example.com');
        $this->assertNotNull($account);
        $this->assertNotEmpty($account->device_uuid);
    }

    public function test_link_action_is_admin_only_on_the_receipt_view(): void
    {
        $receipt = MaxiReceipt::factory()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ViewMaxiReceipt::class, ['record' => $receipt->getKey()])
            ->assertActionHidden('linkTransaction');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewMaxiReceipt::class, ['record' => $receipt->getKey()])
            ->assertActionVisible('linkTransaction');
    }

    public function test_sync_actions_are_admin_only_on_the_accounts_table(): void
    {
        $account = MaxiAccount::factory()->withValidToken()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ListMaxiAccounts::class)
            ->assertTableActionHidden('sync', $account);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListMaxiAccounts::class)
            ->assertTableActionVisible('sync', $account);
    }
}
