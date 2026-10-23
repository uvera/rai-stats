<?php

namespace Database\Factories;

use App\Enums\PromotionSource;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryPromotion>
 */
class GroceryPromotionFactory extends Factory
{
    protected $model = GroceryPromotion::class;

    public function definition(): array
    {
        return [
            'grocery_account_id' => GroceryAccount::factory()->metro(),
            'store_number' => '22',
            'shop_variant_id' => 'BTY-'.$this->faker->unique()->numerify('######'),
            'source' => PromotionSource::Ranked,
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
