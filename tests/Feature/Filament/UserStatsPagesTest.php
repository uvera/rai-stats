<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\UserStats;
use App\Filament\Pages\UserStatsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserStatsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_another_users_stats_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $target = User::factory()->create();

        Livewire::test(UserStats::class, ['record' => $target])
            ->assertOk()
            ->assertDontSeeText('Leaderboard');
    }

    public function test_non_admin_cannot_access_another_users_stats_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::User]));
        $target = User::factory()->create();

        Livewire::test(UserStats::class, ['record' => $target])->assertForbidden();
    }

    public function test_user_stats_index_renders_for_admin_and_links_to_users(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $target = User::factory()->create(['name' => 'Jane Doe']);

        Livewire::test(UserStatsIndex::class)
            ->assertOk()
            ->assertSeeText('Jane Doe');
    }

    public function test_non_admin_cannot_access_user_stats_index(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::User]));

        Livewire::test(UserStatsIndex::class)->assertForbidden();
    }
}
