<?php

namespace Tests\Unit\Support;

use App\Models\ProductCategory;
use App\Models\ProductCategoryRule;
use App\Support\ProductCategorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategorizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_case_insensitively_on_substring(): void
    {
        $dairy = ProductCategory::factory()->create(['name' => 'Dairy']);
        ProductCategoryRule::factory()->for($dairy)->create(['pattern' => 'jogurt']);

        $this->assertSame($dairy->id, (new ProductCategorizer)->categorize('Jogurt 2,8% MM 1kg'));
    }

    public function test_returns_null_when_nothing_matches(): void
    {
        ProductCategory::factory()->has(ProductCategoryRule::factory()->state(['pattern' => 'mleko']), 'rules')->create();

        $this->assertNull((new ProductCategorizer)->categorize('Hleb sa krompirom'));
    }

    public function test_higher_priority_rule_wins(): void
    {
        $produce = ProductCategory::factory()->create(['name' => 'Produce']);
        $fruit = ProductCategory::factory()->create(['name' => 'Fruit']);

        ProductCategoryRule::factory()->for($produce)->create(['pattern' => 'ananas', 'priority' => 1]);
        ProductCategoryRule::factory()->for($fruit)->create(['pattern' => 'ananas', 'priority' => 10]);

        $this->assertSame($fruit->id, (new ProductCategorizer)->categorize('Ananas svez ociscen 525g'));
    }
}
