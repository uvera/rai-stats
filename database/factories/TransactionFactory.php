<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'date' => $this->faker->dateTimeBetween('-3 months'),
            'amount_cents' => $this->faker->numberBetween(-2000000, 2000000),
            'currency_code' => 'RSD',
            'place' => $this->faker->company(),
            'reference' => $this->faker->uuid(),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(TransactionType::cases()),
            'bank_transaction_id' => $this->faker->unique()->uuid(),
            'dedup_key' => $this->faker->unique()->uuid(),
        ];
    }
}
