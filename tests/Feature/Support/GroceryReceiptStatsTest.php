<?php

namespace Tests\Feature\Support;

use App\Enums\ReceiptProvider;
use App\Filament\Pages\FamilyStats;
use App\Filament\Pages\MyStats;
use App\Models\Account;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Support\GroceryReceiptStats;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroceryReceiptStatsTest extends TestCase
{
    use RefreshDatabase;

    private function stats(): GroceryReceiptStats
    {
        return new GroceryReceiptStats(
            from: CarbonImmutable::parse('2026-01-01'),
            to: CarbonImmutable::parse('2026-12-31'),
        );
    }

    public function test_product_category_spend_groups_items(): void
    {
        $dairy = ProductCategory::factory()->create(['name' => 'Dairy']);
        $receipt = GroceryReceipt::factory()->create(['purchased_at' => '2026-06-01', 'total_cents' => 30000]);

        GroceryReceiptItem::factory()->for($receipt, 'receipt')->create(['total_cents' => 20000, 'product_category_id' => $dairy->id]);
        GroceryReceiptItem::factory()->for($receipt, 'receipt')->create(['total_cents' => 10000, 'product_category_id' => null]);

        $rows = collect($this->stats()->productCategorySpend())->keyBy('category_name');

        $this->assertSame(20000, $rows['Dairy']['spend_cents']);
        $this->assertSame(10000, $rows['Uncategorized']['spend_cents']);
    }

    public function test_overview_totals(): void
    {
        GroceryReceipt::factory()->count(2)->create(['purchased_at' => '2026-05-01', 'total_cents' => 50000]);

        $stats = $this->stats();

        $this->assertSame(2, $stats->receiptCount());
        $this->assertSame(100000, $stats->totalSpentCents());
        $this->assertSame(50000, $stats->averageBasketCents());
    }

    public function test_overview_matches_the_individual_methods(): void
    {
        $linked = GroceryReceipt::factory()->create([
            'purchased_at' => '2026-05-01', 'total_cents' => 40000,
            'transaction_id' => Transaction::factory()->create()->id,
        ]);
        $unlinked = GroceryReceipt::factory()->create(['purchased_at' => '2026-05-02', 'total_cents' => 60000]);

        GroceryReceiptItem::factory()->for($linked, 'receipt')->create(['total_cents' => 12000, 'vat_rate' => 20]);
        GroceryReceiptItem::factory()->for($unlinked, 'receipt')->create(['total_cents' => 11000, 'vat_rate' => 10]);
        GroceryReceiptItem::factory()->for($unlinked, 'receipt')->create(['total_cents' => 5000, 'vat_rate' => null]);

        $stats = $this->stats();
        $overview = $stats->overview();

        $this->assertSame($stats->receiptCount(), $overview['receipt_count']);
        $this->assertSame($stats->totalSpentCents(), $overview['total_spent_cents']);
        $this->assertSame($stats->averageBasketCents(), $overview['average_basket_cents']);
        $this->assertSame($stats->totalVatCents(), $overview['total_vat_cents']);
        $this->assertSame($stats->linkedPercentage(), $overview['linked_percentage']);

        // VAT: 12000 - 12000/1.2 = 2000; 11000 - 11000/1.1 = 1000; null skipped.
        $this->assertSame(3000, $overview['total_vat_cents']);
        $this->assertSame(50, $overview['linked_percentage']);
    }

    public function test_basket_size_over_time_buckets_by_month(): void
    {
        GroceryReceipt::factory()->create(['purchased_at' => '2026-03-10', 'total_cents' => 10000]);
        GroceryReceipt::factory()->create(['purchased_at' => '2026-03-20', 'total_cents' => 30000]);
        GroceryReceipt::factory()->create(['purchased_at' => '2026-04-05', 'total_cents' => 20000]);

        $rows = collect($this->stats()->basketSizeOverTime())->keyBy('period');

        $this->assertSame(2, $rows['Mar 2026']['receipts']);
        $this->assertSame(40000, $rows['Mar 2026']['spend_cents']);
        $this->assertSame(1, $rows['Apr 2026']['receipts']);
    }

    public function test_maxi_widgets_are_not_added_to_the_shared_stats_pages(): void
    {
        foreach ([MyStats::class, FamilyStats::class] as $page) {
            $widgets = array_merge(
                (new $page)->getStatsWidgets(),
                (new $page)->getChartWidgets(),
                (new $page)->getTableWidgets(),
            );

            foreach ($widgets as $widget) {
                $this->assertStringNotContainsString('Widgets\\Groceries', $widget);
            }
        }
    }

    public function test_period_comparison_uses_the_previous_window_and_last_year(): void
    {
        GroceryReceipt::factory()->create(['purchased_at' => '2026-01-15', 'total_cents' => 20000]);
        GroceryReceipt::factory()->create(['purchased_at' => '2025-12-15', 'total_cents' => 10000]);
        GroceryReceipt::factory()->create(['purchased_at' => '2025-01-15', 'total_cents' => 5000]);

        $comparison = (new GroceryReceiptStats(
            from: CarbonImmutable::parse('2026-01-01'),
            to: CarbonImmutable::parse('2026-01-31'),
        ))->periodComparison();

        $this->assertSame(20000, $comparison['current']['total_spent_cents']);
        $this->assertSame(1, $comparison['current']['receipt_count']);
        $this->assertSame(20000, $comparison['current']['average_basket_cents']);
        $this->assertSame(10000, $comparison['previous']['total_spent_cents']);
        $this->assertSame(5000, $comparison['last_year']['total_spent_cents']);
    }

    public function test_spend_by_weekday_is_monday_first_and_splits_currencies(): void
    {
        GroceryReceipt::factory()->create(['purchased_at' => '2026-06-01 11:00:00', 'total_cents' => 15000, 'currency_code' => 'RSD']);
        GroceryReceipt::factory()->create(['purchased_at' => '2026-06-07 18:00:00', 'total_cents' => 5000, 'currency_code' => 'RSD']);
        GroceryReceipt::factory()->create(['purchased_at' => '2026-05-01', 'total_cents' => 99999]);

        $days = collect((new GroceryReceiptStats(
            from: CarbonImmutable::parse('2026-06-01'),
            to: CarbonImmutable::parse('2026-06-07'),
        ))->spendByWeekday());

        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], $days->pluck('weekday')->all());
        $this->assertSame(1, $days[0]['receipts']);
        $this->assertSame(15000, $days[0]['spend_cents']['RSD']);
        $this->assertSame(0, $days[1]['receipts']);
        $this->assertSame(5000, $days[6]['spend_cents']['RSD']);
    }

    public function test_spend_by_store_keeps_formats_apart(): void
    {
        GroceryReceipt::factory()->create([
            'purchased_at' => '2026-03-01', 'store_name' => 'Maxi 12', 'store_format' => 'MAXI', 'total_cents' => 10000,
        ]);
        GroceryReceipt::factory()->create([
            'purchased_at' => '2026-03-02', 'store_name' => 'Maxi 12', 'store_format' => 'MAXI', 'total_cents' => 30000,
        ]);
        GroceryReceipt::factory()->create([
            'purchased_at' => '2026-03-03', 'store_name' => 'Maxi 12', 'store_format' => 'MAXI EXPRESS', 'total_cents' => 8000,
        ]);

        $rows = collect($this->stats()->spendByStore())->keyBy(fn (array $row) => $row['store_name'].'|'.$row['store_format']);

        $this->assertSame(2, $rows['Maxi 12|MAXI']['receipts']);
        $this->assertSame(40000, $rows['Maxi 12|MAXI']['spend_cents']);
        $this->assertSame(20000, $rows['Maxi 12|MAXI']['avg_basket_cents']);
        $this->assertSame(8000, $rows['Maxi 12|MAXI EXPRESS']['spend_cents']);
    }

    public function test_staples_are_products_bought_in_most_months_of_the_range(): void
    {
        foreach (['2026-01-10', '2026-02-10', '2026-03-10', '2026-04-10'] as $date) {
            $receipt = GroceryReceipt::factory()->create(['purchased_at' => $date]);
            GroceryReceiptItem::factory()->for($receipt, 'receipt')->create([
                'name' => 'Milk', 'quantity' => 1, 'total_cents' => 1000,
            ]);
        }

        $once = GroceryReceipt::factory()->create(['purchased_at' => '2026-01-11']);
        GroceryReceiptItem::factory()->for($once, 'receipt')->create([
            'name' => 'Birthday cake', 'quantity' => 1, 'total_cents' => 9000,
        ]);

        $rows = collect((new GroceryReceiptStats(
            from: CarbonImmutable::parse('2026-01-01'),
            to: CarbonImmutable::parse('2026-06-30'),
        ))->staples())->keyBy('name');

        $this->assertSame(4, $rows['Milk']['months']);
        $this->assertSame(4000, $rows['Milk']['spend_cents']);
        $this->assertEquals(4.0, $rows['Milk']['quantity']);
        $this->assertArrayNotHasKey('Birthday cake', $rows->all());
    }

    public function test_staples_are_empty_when_the_range_is_a_single_month(): void
    {
        $receipt = GroceryReceipt::factory()->create(['purchased_at' => '2026-03-10']);
        GroceryReceiptItem::factory()->for($receipt, 'receipt')->create(['name' => 'Milk']);

        $rows = (new GroceryReceiptStats(
            from: CarbonImmutable::parse('2026-03-01'),
            to: CarbonImmutable::parse('2026-03-31'),
        ))->staples();

        $this->assertSame([], $rows);
    }

    public function test_card_spend_share_uses_linked_receipts_over_household_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency_code' => 'RSD']);
        $eur = Account::factory()->for($user)->create(['currency_code' => 'EUR']);

        $payment = Transaction::factory()->for($account)->for($user)->create([
            'amount_cents' => -100000, 'currency_code' => 'RSD', 'date' => '2026-06-01',
        ]);
        Transaction::factory()->for($eur)->for($user)->create([
            'amount_cents' => -5000, 'currency_code' => 'EUR', 'date' => '2026-06-01',
        ]);

        GroceryReceipt::factory()->create([
            'purchased_at' => '2026-06-01', 'total_cents' => 40000, 'currency_code' => 'RSD',
            'provider' => ReceiptProvider::Maxi, 'transaction_id' => $payment->id,
        ]);
        GroceryReceipt::factory()->create([
            'purchased_at' => '2026-06-02', 'total_cents' => 60000, 'currency_code' => 'RSD',
            'transaction_id' => null,
        ]);
        GroceryReceipt::factory()->metro()->create([
            'purchased_at' => '2026-06-03', 'total_cents' => 20000, 'currency_code' => 'RSD',
            'transaction_id' => $payment->id,
        ]);

        $share = (new GroceryReceiptStats(
            from: CarbonImmutable::parse('2026-06-01'),
            to: CarbonImmutable::parse('2026-06-30'),
            provider: ReceiptProvider::Maxi,
        ))->cardSpendShare();

        $this->assertSame(40000, $share['RSD']['linked_cents']);
        $this->assertSame(100000, $share['RSD']['expense_cents']);
        $this->assertSame(40, $share['RSD']['share']);
        $this->assertSame(0, $share['EUR']['linked_cents']);
        $this->assertSame(0, $share['EUR']['share']);
    }
}
