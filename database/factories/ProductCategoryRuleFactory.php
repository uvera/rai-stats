<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\ProductCategoryRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryRuleFactory extends Factory
{
    protected $model = ProductCategoryRule::class;

    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'pattern' => $this->faker->unique()->word(),
            'priority' => 0,
        ];
    }
}
