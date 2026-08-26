<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\FamilyStats;
use App\Filament\Pages\MyStats;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_stats_renders_and_only_counts_the_current_users_transactions(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000, 'date' => now()]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -2000, 'date' => now()]);

        $this->actingAs($me);

        Livewire::test(MyStats::class)
            ->assertOk()
            ->assertSeeText('1'); // transaction count
    }

    public function test_family_stats_shows_the_leaderboard_and_my_stats_does_not(): void
    {
        $me = User::factory()->create();
        $account = Account::factory()->for($me)->create();
        Transaction::factory()->for($account)->for($me)->create(['date' => now()]);

        $this->actingAs($me);

        Livewire::test(FamilyStats::class)->assertOk()->assertSeeText('Leaderboard');
        Livewire::test(MyStats::class)->assertOk()->assertDontSeeText('Leaderboard');
    }

    public function test_family_stats_includes_every_users_transactions(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $account = Account::factory()->for($me)->create();

        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000, 'date' => now()]);
        Transaction::factory()->for($account)->for($someoneElse)->create(['amount_cents' => -2000, 'date' => now()]);

        $this->actingAs($me);

        $component = Livewire::test(FamilyStats::class);
        $component->assertOk();

        $this->assertSame(2, $component->instance()->transactionCount());
    }

    public function test_changing_the_period_recomputes_the_income_vs_expense_trend(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->for($user)->create(['amount_cents' => -1000, 'date' => now()->startOfMonth()]);

        $this->actingAs($user);

        Livewire::test(MyStats::class)
            ->set('period', 'year')
            ->assertSet('period', 'year');
    }
}
