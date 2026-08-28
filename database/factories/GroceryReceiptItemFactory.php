<?php

namespace Database\Factories;

use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroceryReceiptItemFactory extends Factory
{
    protected $model = GroceryReceiptItem::class;

    public function definition(): array
    {
        $unit = $this->faker->numberBetween(5000, 200000);

        return [
            'grocery_receipt_id' => GroceryReceipt::factory(),
            'line_no' => $this->faker->unique()->numberBetween(1, 50),
            'name' => $this->faker->words(3, true),
            'quantity' => 1,
            'unit_price_cents' => $unit,
            'net_unit_price_cents' => (int) round($unit / 1.2),
            'total_cents' => $unit,
            'net_total_cents' => (int) round($unit / 1.2),
            'vat_label' => $this->faker->randomElement(['Е', 'Ђ']),
            'vat_rate' => $this->faker->randomElement([10, 20]),
            'product_category_id' => null,
            'category_source' => null,
        ];
    }
}
