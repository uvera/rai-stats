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
            // Excludes Reserved: that's not a real Raiffeisen transaction
            // type and every stats query excludes it, so picking it up here
            // by chance would make tests relying on the default type flaky.
            // Use the reserved() state explicitly where a reserved row is
            // actually wanted.
            'type' => $this->faker->randomElement([
                TransactionType::Pos,
                TransactionType::Other,
                TransactionType::ExchBuy,
                TransactionType::ExchSell,
                TransactionType::Income,
                TransactionType::IncomeCash,
            ]),
            'bank_transaction_id' => $this->faker->unique()->uuid(),
            'dedup_key' => $this->faker->unique()->uuid(),
        ];
    }

    public function reserved(): static
    {
        return $this->state(['type' => TransactionType::Reserved]);
    }
}
