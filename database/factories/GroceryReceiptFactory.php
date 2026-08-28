<?php

namespace Database\Factories;

use App\Enums\ReceiptProvider;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GroceryReceiptFactory extends Factory
{
    protected $model = GroceryReceipt::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(20000, 800000);

        return [
            'grocery_account_id' => GroceryAccount::factory(),
            'provider' => ReceiptProvider::Maxi,
            'external_ref' => hash('sha256', Str::random(20)),
            'pfr_number' => strtoupper(Str::random(8)).'-'.strtoupper(Str::random(8)).'-'.$this->faker->numberBetween(1, 99999),
            'purs_vl' => null,
            'store_name' => 'Maxi '.$this->faker->numberBetween(1, 300),
            'store_address' => $this->faker->streetAddress(),
            'store_format' => $this->faker->randomElement(['MAXI', 'MEGA MAXI', 'MAXI EXPRESS']),
            'purchased_at' => $this->faker->dateTimeBetween('-4 months'),
            'total_cents' => $total,
            'net_total_cents' => (int) round($total / 1.2),
            'currency_code' => 'RSD',
            'transaction_id' => null,
            'match_source' => null,
            'raw_text' => null,
            'synced_at' => now(),
        ];
    }

    public function metro(): static
    {
        return $this->state(fn () => [
            'provider' => ReceiptProvider::Metro,
            'external_ref' => 'SRB_22_11_'.$this->faker->numberBetween(100000, 999999).'_20260101120000',
            'pfr_number' => null,
            'store_name' => 'Metro 22',
            'store_address' => null,
            'store_format' => null,
        ]);
    }
}
