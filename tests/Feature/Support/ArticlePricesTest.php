<?php

namespace Tests\Feature\Support;

use App\Enums\ReceiptProvider;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use App\Support\ArticlePrices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlePricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_by_exact_name_and_summarizes_unit_prices(): void
    {
        $older = GroceryReceipt::factory()->create(['purchased_at' => '2026-03-01 10:00:00']);
        $newer = GroceryReceipt::factory()->create(['purchased_at' => '2026-06-01 10:00:00']);
        $metro = GroceryReceipt::factory()->metro()->create(['purchased_at' => '2026-07-01 10:00:00']);

        $firstMilk = GroceryReceiptItem::factory()->for($older, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Milk',
            'unit_price_cents' => 10000,
            'quantity' => 2,
            'total_cents' => 20000,
        ]);
        GroceryReceiptItem::factory()->for($newer, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Milk',
            'unit_price_cents' => 15000,
            'quantity' => 1,
            'total_cents' => 15000,
        ]);
        $metroMilk = GroceryReceiptItem::factory()->for($metro, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Milk',
            'unit_price_cents' => 20000,
            'quantity' => 1,
            'total_cents' => 20000,
        ]);
        GroceryReceiptItem::factory()->for($older, 'receipt')->create([
            'line_no' => 2,
            'name' => 'Bread',
            'unit_price_cents' => 8000,
            'quantity' => 1,
            'total_cents' => 8000,
        ]);

        $rows = collect(ArticlePrices::summaries())->keyBy('name');

        $this->assertCount(2, $rows);
        $this->assertSame($firstMilk->id, $rows['Milk']['id']);
        $this->assertSame(3, $rows['Milk']['times_bought']);
        $this->assertSame(10000, $rows['Milk']['min_unit_price_cents']);
        $this->assertSame(20000, $rows['Milk']['max_unit_price_cents']);
        $this->assertSame(15000, $rows['Milk']['avg_unit_price_cents']);
        $this->assertSame(20000, $rows['Milk']['last_unit_price_cents']);
        $this->assertSame(100.0, $rows['Milk']['raise_since_cheapest_percent']);
        $this->assertSame('2026-07-01 10:00:00', $rows['Milk']['last_purchased_at']->format('Y-m-d H:i:s'));
        $this->assertEqualsCanonicalizing(['Moj Maxi', 'Metro'], $rows['Milk']['providers']);
        $this->assertSame(8000, $rows['Bread']['last_unit_price_cents']);
        $this->assertSame(0.0, $rows['Bread']['raise_since_cheapest_percent']);
        $this->assertSame(1, $rows['Bread']['times_bought']);

        $history = ArticlePrices::history('Milk')->get();

        $this->assertSame(
            [$metroMilk->id, $newer->items()->where('name', 'Milk')->value('id'), $firstMilk->id],
            $history->pluck('id')->all(),
        );
        $this->assertSame('Metro 22', $history->first()->receipt->store_name);
        $this->assertSame(ReceiptProvider::Metro, $history->first()->receipt->provider);
        $this->assertSame($metro->id, $history->first()->grocery_receipt_id);

        $filtered = collect(ArticlePrices::summaries(ReceiptProvider::Metro))->keyBy('name');

        $this->assertCount(1, $filtered);
        $this->assertSame($metroMilk->id, $filtered['Milk']['id']);
        $this->assertSame(1, $filtered['Milk']['times_bought']);
        $this->assertSame(20000, $filtered['Milk']['last_unit_price_cents']);
        $this->assertSame(0.0, $filtered['Milk']['raise_since_cheapest_percent']);
        $this->assertSame(['Metro'], $filtered['Milk']['providers']);
    }

    public function test_last_price_breaks_ties_by_higher_item_id(): void
    {
        $at = '2026-04-01 12:00:00';
        $first = GroceryReceipt::factory()->create(['purchased_at' => $at]);
        $second = GroceryReceipt::factory()->create(['purchased_at' => $at]);

        GroceryReceiptItem::factory()->for($first, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Yogurt',
            'unit_price_cents' => 100,
        ]);
        $later = GroceryReceiptItem::factory()->for($second, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Yogurt',
            'unit_price_cents' => 999,
        ]);

        $summary = ArticlePrices::summary('Yogurt');

        $this->assertSame(999, $summary['last_unit_price_cents']);
        $this->assertSame(899.0, $summary['raise_since_cheapest_percent']);
        $this->assertSame($later->id, ArticlePrices::history('Yogurt')->first()->id);
    }

    public function test_raise_compares_the_last_price_with_the_cheapest_not_the_earliest(): void
    {
        $earliest = GroceryReceipt::factory()->create(['purchased_at' => '2026-01-01 10:00:00']);
        $cheapest = GroceryReceipt::factory()->create(['purchased_at' => '2026-02-01 10:00:00']);
        $latest = GroceryReceipt::factory()->create(['purchased_at' => '2026-03-01 10:00:00']);

        GroceryReceiptItem::factory()->for($earliest, 'receipt')->create([
            'line_no' => 1, 'name' => 'Eggs', 'unit_price_cents' => 20000,
        ]);
        GroceryReceiptItem::factory()->for($cheapest, 'receipt')->create([
            'line_no' => 1, 'name' => 'Eggs', 'unit_price_cents' => 10000,
        ]);
        GroceryReceiptItem::factory()->for($latest, 'receipt')->create([
            'line_no' => 1, 'name' => 'Eggs', 'unit_price_cents' => 15000,
        ]);

        $this->assertSame(50.0, ArticlePrices::summary('Eggs')['raise_since_cheapest_percent']);
        $this->assertSame('+50.0%', ArticlePrices::formatRaise(50.0));
        $this->assertSame('—', ArticlePrices::formatRaise(null));
    }

    public function test_summary_returns_null_for_an_unknown_name(): void
    {
        $this->assertNull(ArticlePrices::summary('Missing'));
    }
}
