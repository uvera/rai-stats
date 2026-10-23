<?php

namespace Database\Factories;

use App\Models\GroceryPromotion;
use App\Models\GroceryPromotionMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryPromotionMatch>
 */
class GroceryPromotionMatchFactory extends Factory
{
    protected $model = GroceryPromotionMatch::class;

    public function definition(): array
    {
        return [
            'grocery_promotion_id' => GroceryPromotion::factory(),
            'article_name' => 'COLLA PARMIGANO REGGIANO 200G',
            'times_bought' => 2,
            'last_purchased_at' => now(),
            'last_unit_price_cents' => 48000,
        ];
    }
}
