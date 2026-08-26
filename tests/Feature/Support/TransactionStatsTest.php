<?php

namespace Tests\Feature\Support;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionStats;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStatsTest extends TestCase
{
    use RefreshDatabase;

    private function stats(?int $userId, string $period = 'month'): TransactionStats
    {
        return new TransactionStats(
            userId: $userId,
            from: CarbonImmutable::parse('2026-01-01'),
            to: CarbonImmutable::parse('2026-12-31'),
            period: $period,
        );
    }

    public function test_spend_per_account_sums_spend_and_income_separately(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['description' => 'Main']);

        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -1000, 'date' => '2026-03-01']);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -500, 'date' => '2026-03-05']);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => 2000, 'date' => '2026-03-10']);

        $rows = $this->stats($user->id)->spendPerAccount();

        $this->assertCount(1, $rows);
        $this->assertSame(1500, $rows[0]['spend_cents']);
        $this->assertSame(2000, $rows[0]['income_cents']);
    }

    public function test_top_places_orders_by_spend_descending(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create(['place' => 'Small Shop', 'amount_cents' => -100]);
        Transaction::factory()->for($account)->for($user)->create(['place' => 'Big Shop', 'amount_cents' => -5000]);
        Transaction::factory()->for($account)->for($user)->create(['place' => 'Big Shop', 'amount_cents' => -1000]);

        $rows = $this->stats($user->id)->topPlaces();

        $this->assertSame('Big Shop', $rows[0]['place']);
        $this->assertSame(6000, $rows[0]['spend_cents']);
        $this->assertSame(2, $rows[0]['transaction_count']);
        $this->assertSame('Small Shop', $rows[1]['place']);
    }

    public function test_income_vs_expense_trend_groups_by_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -1000, 'date' => '2026-01-15']);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => 3000, 'date' => '2026-01-20']);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -2000, 'date' => '2026-02-01']);

        $rows = $this->stats($user->id)->incomeVsExpenseTrend();

        $this->assertCount(2, $rows);
        $this->assertSame(1000, $rows[0]['expense_cents']);
        $this->assertSame(3000, $rows[0]['income_cents']);
        $this->assertSame(2000, $rows[0]['net_cents']);
        $this->assertSame(2000, $rows[1]['expense_cents']);
    }

    public function test_atm_withdrawal_total_matches_place_or_description_keywords(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create([
            'place' => 'bankomat', 'description' => 'ATM withdrawal', 'type' => TransactionType::Other, 'amount_cents' => -20000,
        ]);
        Transaction::factory()->for($account)->for($user)->create([
            'place' => 'Random Shop', 'description' => 'Groceries', 'type' => TransactionType::Pos, 'amount_cents' => -3000,
        ]);

        $this->assertSame(['RSD' => 20000], $this->stats($user->id)->atmWithdrawalTotalsByCurrency());
    }

    public function test_atm_withdrawal_totals_are_grouped_by_currency(): void
    {
        $user = User::factory()->create();
        $rsdAccount = Account::factory()->for($user)->create(['currency_code' => 'RSD']);
        $eurAccount = Account::factory()->for($user)->create(['currency_code' => 'EUR']);

        Transaction::factory()->for($rsdAccount)->for($user)->create([
            'place' => 'bankomat', 'type' => TransactionType::Other, 'amount_cents' => -20000, 'currency_code' => 'RSD',
        ]);
        Transaction::factory()->for($eurAccount)->for($user)->create([
            'place' => 'ATM', 'type' => TransactionType::Other, 'amount_cents' => -5000, 'currency_code' => 'EUR',
        ]);

        $this->assertSame(
            ['EUR' => 5000, 'RSD' => 20000],
            $this->stats($user->id)->atmWithdrawalTotalsByCurrency(),
        );
    }

    public function test_largest_transactions_orders_by_absolute_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $small = Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -500]);
        $big = Transaction::factory()->for($account)->for($user)->create(['amount_cents' => 15000]);

        $rows = $this->stats($user->id)->largestTransactions();

        $this->assertSame($big->id, $rows[0]->id);
        $this->assertSame($small->id, $rows[1]->id);
    }

    public function test_average_spend_ignores_income(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -1000]);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -3000]);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => 100000]);

        $this->assertSame(['RSD' => 2000], $this->stats($user->id)->averageSpendByCurrency());
    }

    public function test_average_spend_is_grouped_by_currency(): void
    {
        $user = User::factory()->create();
        $rsdAccount = Account::factory()->for($user)->create(['currency_code' => 'RSD']);
        $eurAccount = Account::factory()->for($user)->create(['currency_code' => 'EUR']);

        Transaction::factory()->for($rsdAccount)->for($user)->create(['amount_cents' => -1000, 'currency_code' => 'RSD']);
        Transaction::factory()->for($rsdAccount)->for($user)->create(['amount_cents' => -3000, 'currency_code' => 'RSD']);
        Transaction::factory()->for($eurAccount)->for($user)->create(['amount_cents' => -100, 'currency_code' => 'EUR']);

        $this->assertSame(
            ['EUR' => 100, 'RSD' => 2000],
            $this->stats($user->id)->averageSpendByCurrency(),
        );
    }

    public function test_recurring_charges_requires_several_months_of_a_stable_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        foreach (['2026-01-05', '2026-02-05', '2026-03-05'] as $date) {
            Transaction::factory()->for($account)->for($user)->create([
                'place' => 'Streaming Co', 'amount_cents' => -999, 'date' => $date,
            ]);
        }

        Transaction::factory()->for($account)->for($user)->create([
            'place' => 'One Off Shop', 'amount_cents' => -5000, 'date' => '2026-01-10',
        ]);

        $rows = $this->stats($user->id)->recurringCharges();

        $this->assertCount(1, $rows);
        $this->assertSame('Streaming Co', $rows[0]['place']);
        $this->assertSame(3, $rows[0]['months']);
        $this->assertSame(999, $rows[0]['average_cents']);
    }

    public function test_scope_by_user_id_excludes_other_users_while_null_includes_everyone(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -2000]);

        $this->assertSame(1000, $this->stats($me->id)->spendPerAccount()[0]['spend_cents']);
        $this->assertSame(3000, $this->stats(null)->spendPerAccount()[0]['spend_cents']);
    }

    public function test_leaderboard_breaks_down_totals_per_user_regardless_of_constructor_scope(): void
    {
        $me = User::factory()->create(['name' => 'Me']);
        $someoneElse = User::factory()->create(['name' => 'Sibling']);
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -4000]);

        $rows = $this->stats($me->id)->leaderboard();

        $this->assertCount(2, $rows);
        $this->assertSame('Sibling', $rows[0]['name']);
        $this->assertSame(4000, $rows[0]['spend_cents']);
        $this->assertSame('Me', $rows[1]['name']);
    }

    public function test_reserved_transactions_are_excluded_from_every_query(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create([
            'type' => TransactionType::Reserved, 'amount_cents' => -99999,
        ]);

        $this->assertSame(0, $this->stats($user->id)->transactionCount());
    }

    public function test_spend_per_account_query_matches_the_array_version(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['description' => 'Main']);

        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -1000]);
        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => 2500]);

        $rows = $this->stats($user->id)->spendPerAccountQuery()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Main', $rows[0]->description);
        $this->assertSame(1000, (int) $rows[0]->spend_cents);
        $this->assertSame(2500, (int) $rows[0]->income_cents);
    }

    public function test_leaderboard_query_matches_the_array_version(): void
    {
        $me = User::factory()->create(['name' => 'Me']);
        $someoneElse = User::factory()->create(['name' => 'Sibling']);
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -4000]);

        $rows = $this->stats($me->id)->leaderboardQuery()->get();

        $this->assertCount(2, $rows);
        $this->assertSame('Sibling', $rows[0]->name);
        $this->assertSame(4000, (int) $rows[0]->spend_cents);
    }

    public function test_largest_transactions_query_orders_by_absolute_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $small = Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -500]);
        $big = Transaction::factory()->for($account)->for($user)->create(['amount_cents' => 15000]);

        $rows = $this->stats($user->id)->largestTransactionsQuery()->get();

        $this->assertSame($big->id, $rows[0]->id);
        $this->assertSame($small->id, $rows[1]->id);
    }

    public function test_recurring_charges_query_matches_the_array_version(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        foreach (['2026-01-05', '2026-02-05', '2026-03-05'] as $date) {
            Transaction::factory()->for($account)->for($user)->create([
                'place' => 'Streaming Co', 'amount_cents' => -999, 'date' => $date,
            ]);
        }

        Transaction::factory()->for($account)->for($user)->create([
            'place' => 'One Off Shop', 'amount_cents' => -5000, 'date' => '2026-01-10',
        ]);

        $rows = $this->stats($user->id)->recurringChargesQuery()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Streaming Co', $rows[0]->place);
        $this->assertSame(3, (int) $rows[0]->months);
    }
}
