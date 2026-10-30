<?php

namespace Tests\Feature\Support;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AccountSpendComparison;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSpendComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function comparison(Account $accountA, Account $accountB, string $period = 'month'): AccountSpendComparison
    {
        return new AccountSpendComparison(
            accountA: $accountA,
            accountB: $accountB,
            from: CarbonImmutable::parse('2026-01-01'),
            to: CarbonImmutable::parse('2026-12-31'),
            period: $period,
        );
    }

    public function test_totals_and_categories_keep_each_account_and_fill_a_missing_side(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create(['currency_code' => 'RSD']);
        $accountB = Account::factory()->for($user)->create(['currency_code' => 'RSD']);
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        Transaction::factory()->for($accountA)->for($user)->create([
            'category_id' => $groceries->id,
            'amount_cents' => -1000,
            'date' => '2026-03-01',
        ]);
        Transaction::factory()->for($accountA)->for($user)->create([
            'category_id' => null,
            'amount_cents' => -500,
            'date' => '2026-03-02',
        ]);
        Transaction::factory()->for($accountB)->for($user)->create([
            'category_id' => $groceries->id,
            'amount_cents' => -300,
            'date' => '2026-03-03',
        ]);
        Transaction::factory()->for($accountA)->for($user)->create([
            'category_id' => $groceries->id,
            'amount_cents' => 9000,
            'date' => '2026-03-04',
        ]);
        Transaction::factory()->for($accountA)->for($user)->create([
            'category_id' => $groceries->id,
            'amount_cents' => -8000,
            'date' => '2025-12-01',
        ]);

        $comparison = $this->comparison($accountA, $accountB);
        $totals = $comparison->totals();

        $this->assertSame(1500, $totals['a']['spend_cents']);
        $this->assertSame(300, $totals['b']['spend_cents']);
        $this->assertSame(1200, $totals['difference_cents']);
        $this->assertSame('RSD', $totals['a']['currency_code']);
        $this->assertSame('RSD', $totals['b']['currency_code']);

        $rows = $comparison->byCategory();

        $this->assertSame('Groceries', $rows[0]['category_name']);
        $this->assertSame(1000, $rows[0]['spend_a_cents']);
        $this->assertSame(300, $rows[0]['spend_b_cents']);
        $this->assertSame(700, $rows[0]['difference_cents']);
        $this->assertSame('Uncategorized', $rows[1]['category_name']);
        $this->assertSame(500, $rows[1]['spend_a_cents']);
        $this->assertSame(0, $rows[1]['spend_b_cents']);
        $this->assertSame(500, $rows[1]['difference_cents']);
    }

    public function test_over_time_includes_a_period_where_only_one_account_spent(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();

        Transaction::factory()->for($accountA)->for($user)->create([
            'amount_cents' => -1000,
            'date' => '2026-01-15',
        ]);
        Transaction::factory()->for($accountB)->for($user)->create([
            'amount_cents' => -400,
            'date' => '2026-02-02',
        ]);

        $rows = $this->comparison($accountA, $accountB)->overTime();

        $this->assertCount(2, $rows);
        $this->assertSame('Jan 2026', $rows[0]['label']);
        $this->assertSame(1000, $rows[0]['spend_a_cents']);
        $this->assertSame(0, $rows[0]['spend_b_cents']);
        $this->assertSame('Feb 2026', $rows[1]['label']);
        $this->assertSame(0, $rows[1]['spend_a_cents']);
        $this->assertSame(400, $rows[1]['spend_b_cents']);
    }

    public function test_reserved_rows_and_other_accounts_are_excluded(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();
        $other = Account::factory()->for($user)->create();

        Transaction::factory()->for($accountA)->for($user)->create([
            'amount_cents' => -1000,
            'date' => '2026-04-01',
        ]);
        Transaction::factory()->for($accountA)->for($user)->reserved()->create([
            'amount_cents' => -99999,
            'date' => '2026-04-02',
        ]);
        Transaction::factory()->for($other)->for($user)->create([
            'amount_cents' => -88888,
            'date' => '2026-04-03',
        ]);

        $totals = $this->comparison($accountA, $accountB)->totals();

        $this->assertSame(1000, $totals['a']['spend_cents']);
        $this->assertSame(0, $totals['b']['spend_cents']);
    }

    public function test_difference_is_omitted_when_currencies_differ(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create(['currency_code' => 'RSD']);
        $accountB = Account::factory()->for($user)->create(['currency_code' => 'EUR']);
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        Transaction::factory()->for($accountA)->for($user)->create([
            'category_id' => $groceries->id,
            'amount_cents' => -1000,
            'date' => '2026-05-01',
        ]);
        Transaction::factory()->for($accountB)->for($user)->create([
            'category_id' => $groceries->id,
            'amount_cents' => -200,
            'date' => '2026-05-02',
        ]);

        $comparison = $this->comparison($accountA, $accountB);

        $this->assertFalse($comparison->sameCurrency());
        $this->assertNull($comparison->totals()['difference_cents']);
        $this->assertNull($comparison->byCategory()[0]['difference_cents']);
        $this->assertSame(1000, $comparison->totals()['a']['spend_cents']);
        $this->assertSame(200, $comparison->totals()['b']['spend_cents']);
    }
}
