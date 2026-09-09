<?php

namespace Tests\Feature\Filament;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_does_not_render_any_stats_widgets_or_run_their_queries(): void
    {
        $me = User::factory()->withMfa()->create();
        $sibling = User::factory()->create();

        $account = Account::factory()->for($sibling)->create();
        Transaction::factory()->for($account)->for($sibling)->create(['amount_cents' => -12345]);

        $this->actingAs($me);

        $ranQuery = false;
        DB::listen(function ($query) use (&$ranQuery) {
            if (str_contains($query->sql, 'transactions') && str_contains($query->sql, 'sum')) {
                $ranQuery = true;
            }
        });

        $this->get('/admin')->assertOk();

        $this->assertFalse($ranQuery, 'The Dashboard ran a transactions aggregate - a stats widget leaked onto it.');
    }

    public function test_the_panel_registers_no_app_widgets_for_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $ours = collect(Filament::getWidgets())
            ->map(fn ($widget) => is_string($widget) ? $widget : $widget->widget)
            ->filter(fn (string $class) => str_starts_with($class, 'App\\Filament\\Widgets\\'));

        $this->assertTrue($ours->isEmpty(), 'App widgets are registered for discovery: '.$ours->implode(', '));
    }
}
