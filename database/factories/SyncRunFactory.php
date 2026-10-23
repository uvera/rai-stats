<?php

namespace Database\Factories;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    protected $model = SyncRun::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => SyncKind::GrocerySync,
            'status' => SyncRunStatus::Succeeded,
            'grocery_account_id' => null,
            'summary' => 'Imported 1 receipt',
            'message' => null,
            'details' => ['imported' => 1, 'total_invoices' => 4],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
