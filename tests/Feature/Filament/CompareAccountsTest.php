<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CompareAccounts;
use App\Filament\Widgets\Compare\CompareSpendByCategoryTable;
use App\Filament\Widgets\Compare\CompareSpendOverTimeChart;
use App\Filament\Widgets\Compare\CompareSpendOverview;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompareAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_for_a_signed_in_user(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CompareAccounts::class)->assertOk();

        $filters = [
            'accountA' => null,
            'accountB' => null,
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'period' => 'month',
        ];

        Livewire::test(CompareSpendOverview::class, ['pageFilters' => $filters])->assertOk();
        Livewire::test(CompareSpendOverTimeChart::class, ['pageFilters' => $filters])->assertOk();
        Livewire::test(CompareSpendByCategoryTable::class, ['pageFilters' => $filters])->assertOk();
    }

    public function test_overview_shows_both_account_totals_once_both_are_selected(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create(['description' => 'Everyday']);
        $accountB = Account::factory()->for($user)->create(['description' => 'Travel']);

        Transaction::factory()->for($accountA)->for($user)->create([
            'amount_cents' => -1500,
            'date' => now(),
        ]);
        Transaction::factory()->for($accountB)->for($user)->create([
            'amount_cents' => -300,
            'date' => now(),
        ]);

        $this->actingAs($user);

        $filters = [
            'accountA' => $accountA->id,
            'accountB' => $accountB->id,
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'period' => 'month',
        ];

        Livewire::test(CompareAccounts::class)
            ->set('filters.accountA', $accountA->id)
            ->set('filters.accountB', $accountB->id)
            ->assertSet('filters.accountA', $accountA->id)
            ->assertSet('filters.accountB', $accountB->id)
            ->assertSee(CompareSpendOverview::class);

        // Nested widgets render in their own Livewire component, so the
        // amounts are asserted on the overview the page mounts with these filters.
        Livewire::test(CompareSpendOverview::class, ['pageFilters' => $filters])
            ->assertOk()
            ->assertSee('Everyday')
            ->assertSee('Travel')
            ->assertSee('15.00')
            ->assertSee('3.00');

        Livewire::test(CompareSpendOverTimeChart::class, ['pageFilters' => $filters])
            ->assertOk();

        Livewire::test(CompareSpendByCategoryTable::class, ['pageFilters' => $filters])
            ->assertOk()
            ->assertSee('Uncategorized')
            ->assertSee('15.00')
            ->assertSee('3.00');
    }
}
