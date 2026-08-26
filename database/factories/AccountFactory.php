<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'number' => $this->faker->unique()->numerify('2650000######'),
            'description' => $this->faker->words(3, true),
            'currency_code' => 'RSD',
            'currency_code_numeric' => '941',
            'product_core_id' => (string) $this->faker->numberBetween(1, 99),
        ];
    }
}
