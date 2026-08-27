<?php

namespace Tests\Feature\Support;

use App\Filament\Pages\FamilyStats;
use App\Filament\Pages\MyStats;
use App\Models\MaxiReceipt;
use App\Models\MaxiReceiptItem;
use App\Models\ProductCategory;
use App\Support\MaxiReceiptStats;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaxiReceiptStatsTest extends TestCase
{
    use RefreshDatabase;

    private function stats(): MaxiReceiptStats
    {
        return new MaxiReceiptStats(
            from: CarbonImmutable::parse('2026-01-01'),
            to: CarbonImmutable::parse('2026-12-31'),
        );
    }

    public function test_product_category_spend_groups_items(): void
    {
        $dairy = ProductCategory::factory()->create(['name' => 'Dairy']);
        $receipt = MaxiReceipt::factory()->create(['purchased_at' => '2026-06-01', 'total_cents' => 30000]);

        MaxiReceiptItem::factory()->for($receipt, 'receipt')->create(['total_cents' => 20000, 'product_category_id' => $dairy->id]);
        MaxiReceiptItem::factory()->for($receipt, 'receipt')->create(['total_cents' => 10000, 'product_category_id' => null]);

        $rows = collect($this->stats()->productCategorySpend())->keyBy('category_name');

        $this->assertSame(20000, $rows['Dairy']['spend_cents']);
        $this->assertSame(10000, $rows['Uncategorized']['spend_cents']);
    }

    public function test_overview_totals(): void
    {
        MaxiReceipt::factory()->count(2)->create(['purchased_at' => '2026-05-01', 'total_cents' => 50000]);

        $stats = $this->stats();

        $this->assertSame(2, $stats->receiptCount());
        $this->assertSame(100000, $stats->totalSpentCents());
        $this->assertSame(50000, $stats->averageBasketCents());
    }

    public function test_basket_size_over_time_buckets_by_month(): void
    {
        MaxiReceipt::factory()->create(['purchased_at' => '2026-03-10', 'total_cents' => 10000]);
        MaxiReceipt::factory()->create(['purchased_at' => '2026-03-20', 'total_cents' => 30000]);
        MaxiReceipt::factory()->create(['purchased_at' => '2026-04-05', 'total_cents' => 20000]);

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
                $this->assertStringNotContainsString('Widgets\\Maxi', $widget);
            }
        }
    }
}
