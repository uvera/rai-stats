<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\ProductCategory;
use App\Support\ProductCategorizer;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_categories_with_rules_and_is_idempotent(): void
    {
        (new ProductCategorySeeder)->run();
        (new ProductCategorySeeder)->run();

        $this->assertGreaterThan(0, ProductCategory::count());
        $this->assertSame(
            ProductCategory::firstWhere('name', 'Mlečni proizvodi')->id,
            (new ProductCategorizer)->categorize('Jogurt 2,8% MM 1kg'),
        );
    }
}
