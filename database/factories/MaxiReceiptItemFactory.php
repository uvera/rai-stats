<?php

namespace Database\Factories;

use App\Models\MaxiReceipt;
use App\Models\MaxiReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaxiReceiptItemFactory extends Factory
{
    protected $model = MaxiReceiptItem::class;

    public function definition(): array
    {
        $unit = $this->faker->numberBetween(5000, 200000);

        return [
            'maxi_receipt_id' => MaxiReceipt::factory(),
            'line_no' => $this->faker->unique()->numberBetween(1, 50),
            'name' => $this->faker->words(3, true),
            'quantity' => 1,
            'unit_price_cents' => $unit,
            'total_cents' => $unit,
            'vat_label' => $this->faker->randomElement(['Е', 'Ђ']),
            'vat_rate' => $this->faker->randomElement([10, 20]),
            'product_category_id' => null,
            'category_source' => null,
        ];
    }
}
