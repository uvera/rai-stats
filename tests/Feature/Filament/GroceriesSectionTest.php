<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\GroceryStats;
use App\Filament\Resources\GroceryAccounts\Pages\CreateGroceryAccount;
use App\Filament\Resources\GroceryAccounts\Pages\ListGroceryAccounts;
use App\Filament\Resources\GroceryReceipts\Pages\ListGroceryReceipts;
use App\Filament\Resources\GroceryReceipts\Pages\ViewGroceryReceipt;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroceriesSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_can_view_the_moj_maxi_pages(): void
    {
        $this->actingAs(User::factory()->create());
        GroceryAccount::factory()->create();
        GroceryReceipt::factory()->create();

        Livewire::test(ListGroceryAccounts::class)->assertOk();
        Livewire::test(ListGroceryReceipts::class)->assertOk();
        Livewire::test(GroceryStats::class)->assertOk();
    }

    public function test_non_admin_cannot_create_a_maxi_account(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateGroceryAccount::class)->assertForbidden();
    }

    public function test_admin_can_create_a_maxi_account_and_a_device_uuid_is_generated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateGroceryAccount::class)
            ->set('data.provider', 'maxi')
            ->set('data.label', 'Household Maxi')
            ->set('data.email', 'household@example.com')
            ->set('data.password', 'secret')
            ->call('create')
            ->assertHasNoFormErrors();

        $account = GroceryAccount::firstWhere('email', 'household@example.com');
        $this->assertNotNull($account);
        $this->assertNotEmpty($account->device_uuid);
    }

    public function test_link_action_is_admin_only_on_the_receipt_view(): void
    {
        $receipt = GroceryReceipt::factory()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ViewGroceryReceipt::class, ['record' => $receipt->getKey()])
            ->assertActionHidden('linkTransaction');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewGroceryReceipt::class, ['record' => $receipt->getKey()])
            ->assertActionVisible('linkTransaction');
    }

    public function test_sync_actions_are_admin_only_on_the_accounts_table(): void
    {
        $account = GroceryAccount::factory()->withValidToken()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ListGroceryAccounts::class)
            ->assertTableActionHidden('sync', $account);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListGroceryAccounts::class)
            ->assertTableActionVisible('sync', $account);
    }
}
