<?php

namespace Database\Factories;

use App\Models\MaxiAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaxiAccountFactory extends Factory
{
    protected $model = MaxiAccount::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->firstName().' — Maxi',
            'email' => $this->faker->unique()->safeEmail(),
            'access_token' => null,
            'token_expires_at' => null,
            'device_uuid' => bin2hex(random_bytes(8)),
            'user_id' => null,
            'last_synced_at' => null,
        ];
    }

    public function withValidToken(): static
    {
        return $this->state([
            'access_token' => 'header.'.base64_encode(json_encode(['exp' => now()->addYear()->timestamp])).'.sig',
            'token_expires_at' => now()->addYear(),
        ]);
    }
}
