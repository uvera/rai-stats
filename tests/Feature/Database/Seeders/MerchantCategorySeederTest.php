<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Category;
use Database\Seeders\MerchantCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_categories_and_rules_from_the_bundled_json_fixture(): void
    {
        (new MerchantCategorySeeder)->run();

        $this->assertGreaterThan(0, Category::count());
        $this->assertDatabaseHas('categories', ['name' => 'Groceries']);
        $this->assertDatabaseHas('merchant_category_rules', ['pattern' => 'MAXI']);
    }

    public function test_is_idempotent(): void
    {
        (new MerchantCategorySeeder)->run();
        $countAfterFirstRun = Category::count();

        (new MerchantCategorySeeder)->run();

        $this->assertSame($countAfterFirstRun, Category::count());
    }
}
