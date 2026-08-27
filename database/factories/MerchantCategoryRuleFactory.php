<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MerchantCategoryRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantCategoryRuleFactory extends Factory
{
    protected $model = MerchantCategoryRule::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'pattern' => $this->faker->unique()->word(),
            'priority' => 0,
        ];
    }
}
