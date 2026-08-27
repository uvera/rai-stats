<?php

namespace Database\Factories;

use App\Models\MaxiAccount;
use App\Models\MaxiReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MaxiReceiptFactory extends Factory
{
    protected $model = MaxiReceipt::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(20000, 800000);

        return [
            'maxi_account_id' => MaxiAccount::factory(),
            'invoice_hash' => hash('sha256', Str::random(20)),
            'pfr_number' => strtoupper(Str::random(8)).'-'.strtoupper(Str::random(8)).'-'.$this->faker->numberBetween(1, 99999),
            'purs_vl' => null,
            'store_name' => 'Maxi '.$this->faker->numberBetween(1, 300),
            'store_address' => $this->faker->streetAddress(),
            'store_format' => $this->faker->randomElement(['MAXI', 'MEGA MAXI', 'MAXI EXPRESS']),
            'purchased_at' => $this->faker->dateTimeBetween('-4 months'),
            'total_cents' => $total,
            'currency_code' => 'RSD',
            'transaction_id' => null,
            'match_source' => null,
            'raw_text' => null,
            'synced_at' => now(),
        ];
    }
}
