<?php

namespace Database\Factories;

use App\Enums\ReceiptProvider;
use App\Models\GroceryAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroceryAccountFactory extends Factory
{
    protected $model = GroceryAccount::class;

    public function definition(): array
    {
        return [
            'provider' => ReceiptProvider::Maxi,
            'label' => $this->faker->firstName().' — Maxi',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'device_uuid' => bin2hex(random_bytes(8)),
            'user_id' => null,
            'external_id' => null,
            'last_synced_at' => null,
        ];
    }

    public function metro(): static
    {
        return $this->state(fn () => [
            'provider' => ReceiptProvider::Metro,
            'label' => $this->faker->firstName().' — Metro',
        ]);
    }

    public function withValidToken(): static
    {
        return $this->state([
            'access_token' => 'header.'.base64_encode(json_encode(['exp' => now()->addYear()->timestamp])).'.sig',
            'token_expires_at' => now()->addYear(),
        ]);
    }

    public function withPassword(string $password = 'secret'): static
    {
        return $this->state(['password' => $password]);
    }
}
