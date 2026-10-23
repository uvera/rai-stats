<?php

namespace Database\Factories;

use App\Models\MetroPromotion;
use App\Models\MetroPromotionMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetroPromotionMatch>
 */
class MetroPromotionMatchFactory extends Factory
{
    protected $model = MetroPromotionMatch::class;

    public function definition(): array
    {
        return [
            'metro_promotion_id' => MetroPromotion::factory(),
            'article_name' => 'COLLA PARMIGANO REGGIANO 200G',
            'times_bought' => 2,
            'last_purchased_at' => now(),
            'last_unit_price_cents' => 48000,
        ];
    }
}
