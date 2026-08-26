<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\FamilyStats;
use App\Filament\Pages\MyStats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_stats_page_renders(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MyStats::class)->assertOk();
    }

    public function test_family_stats_shows_the_leaderboard_widget_and_my_stats_does_not(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(FamilyStats::class)->assertOk()->assertSeeText('Leaderboard');
        Livewire::test(MyStats::class)->assertOk()->assertDontSeeText('Leaderboard');
    }

    public function test_changing_the_period_filter_updates_the_shared_filters_state(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MyStats::class)
            ->set('filters.period', 'year')
            ->assertSet('filters.period', 'year');
    }
}
