<?php

namespace Tests\Feature\Console;

use App\Enums\CategorySource;
use App\Models\GroceryReceiptItem;
use App\Models\ProductCategory;
use App\Models\ProductCategoryRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecategorizeGroceryItemsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_rules_to_existing_items(): void
    {
        $dairy = ProductCategory::factory()->create();
        ProductCategoryRule::factory()->for($dairy)->create(['pattern' => 'jogurt']);

        $item = GroceryReceiptItem::factory()->create(['name' => 'Jogurt MM 1kg', 'product_category_id' => null]);

        $this->artisan('groceries:recategorize-items')->assertSuccessful();

        $this->assertSame($dairy->id, $item->fresh()->product_category_id);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $dairy = ProductCategory::factory()->create();
        ProductCategoryRule::factory()->for($dairy)->create(['pattern' => 'jogurt']);
        $item = GroceryReceiptItem::factory()->create(['name' => 'Jogurt MM', 'product_category_id' => null]);

        $this->artisan('groceries:recategorize-items --dry-run')->assertSuccessful();

        $this->assertNull($item->fresh()->product_category_id);
    }

    public function test_skips_manually_categorized_items_unless_forced(): void
    {
        $manual = ProductCategory::factory()->create();
        $ruleCat = ProductCategory::factory()->create();
        ProductCategoryRule::factory()->for($ruleCat)->create(['pattern' => 'jogurt']);

        $item = GroceryReceiptItem::factory()->create([
            'name' => 'Jogurt MM',
            'product_category_id' => $manual->id,
            'category_source' => CategorySource::Manual,
        ]);

        $this->artisan('groceries:recategorize-items')->assertSuccessful();
        $this->assertSame($manual->id, $item->fresh()->product_category_id);

        $this->artisan('groceries:recategorize-items --force')->assertSuccessful();
        $this->assertSame($ruleCat->id, $item->fresh()->product_category_id);
    }
}
