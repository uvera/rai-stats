<?php

namespace Database\Factories;

use App\Models\GroceryAccount;
use App\Models\MetroPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetroPromotion>
 */
class MetroPromotionFactory extends Factory
{
    protected $model = MetroPromotion::class;

    public function definition(): array
    {
        return [
            'grocery_account_id' => GroceryAccount::factory()->metro(),
            'store_number' => '22',
            'shop_variant_id' => 'BTY-'.$this->faker->unique()->numerify('######'),
            'name' => $this->faker->words(4, true),
            'brand' => $this->faker->word(),
            'image_url' => 'https://cdn.metro-group.com/rs/example.png',
            'gross_cents' => 45599,
            'strike_gross_cents' => 59999,
            'starts_on' => '2026-10-21',
            'ends_on' => '2026-10-28',
            'fetched_at' => now(),
        ];
    }
}
