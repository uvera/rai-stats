<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\LargestTransactionsTable;
use App\Filament\Widgets\LeaderboardTable;
use App\Filament\Widgets\SpendByCategoryChart;
use App\Filament\Widgets\SpendPerAccountTable;
use App\Filament\Widgets\StatsOverview;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function pageFilters(): array
    {
        return [
            'from' => now()->subMonths(6)->startOfMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'period' => 'month',
        ];
    }

    public function test_stats_overview_scopes_the_transaction_count_to_the_given_user(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000, 'date' => now()]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -2000, 'date' => now()]);

        $this->actingAs($me);

        Livewire::test(StatsOverview::class, ['userId' => $me->id, 'pageFilters' => $this->pageFilters()])
            ->assertOk()
            ->assertSeeText('1');

        Livewire::test(StatsOverview::class, ['userId' => null, 'pageFilters' => $this->pageFilters()])
            ->assertOk()
            ->assertSeeText('2');
    }

    public function test_spend_per_account_table_lists_accounts_within_the_scope(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['description' => 'Everyday RSD']);

        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -1500, 'date' => now()]);

        $this->actingAs($user);

        Livewire::test(SpendPerAccountTable::class, ['userId' => $user->id, 'pageFilters' => $this->pageFilters()])
            ->assertOk()
            ->assertSeeText('Everyday RSD');
    }

    public function test_largest_transactions_table_lists_transactions_within_the_scope(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create(['place' => 'Big Purchase Inc', 'amount_cents' => -99999, 'date' => now()]);

        $this->actingAs($user);

        Livewire::test(LargestTransactionsTable::class, ['userId' => $user->id, 'pageFilters' => $this->pageFilters()])
            ->assertOk()
            ->assertSeeText('Big Purchase Inc');
    }

    public function test_spend_by_category_chart_renders(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->create(['name' => 'Groceries']);

        Transaction::factory()->for($account)->for($user)->create(['category_id' => $category->id, 'amount_cents' => -1000, 'date' => now()]);

        $this->actingAs($user);

        Livewire::test(SpendByCategoryChart::class, ['userId' => $user->id, 'pageFilters' => $this->pageFilters()])
            ->assertOk();
    }

    public function test_leaderboard_table_lists_every_user_regardless_of_the_scope(): void
    {
        $me = User::factory()->create(['name' => 'Me']);
        $someoneElse = User::factory()->create(['name' => 'Sibling']);
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000, 'date' => now()]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -2000, 'date' => now()]);

        $this->actingAs($me);

        Livewire::test(LeaderboardTable::class, ['userId' => $me->id, 'pageFilters' => $this->pageFilters()])
            ->assertOk()
            ->assertSeeText('Me')
            ->assertSeeText('Sibling');
    }
}
