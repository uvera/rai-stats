<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\IncomeExpenseChart;
use App\Filament\Widgets\LargestTransactionsTable;
use App\Filament\Widgets\LeaderboardTable;
use App\Filament\Widgets\RecurringChargesTable;
use App\Filament\Widgets\SpendPerAccountTable;
use App\Filament\Widgets\SpendPerPlaceOverTimeChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopPlacesChart;
use Filament\Pages\Page;

/**
 * Shared filter bar (date range + period toggle) behind both My Stats and
 * Family Stats. The actual widgets/tables/charts live in
 * app/Filament/Widgets and read the filter state via Filament's native
 * "page filters drive widgets" mechanism (Filament\Widgets\Concerns\
 * InteractsWithPageFilters reads $this->filters, automatically passed down
 * as $pageFilters to every widget rendered on this page).
 *
 * The two pages differ only in which user id widgets are scoped to (passed
 * via getWidgetData(), since it's not something the user edits like the
 * date range) and whether the leaderboard widget is included.
 */
abstract class AbstractStatsPage extends Page
{
    protected string $view = 'filament.pages.stats';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->subMonths(6)->startOfMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'period' => 'month',
        ];
    }

    abstract protected function scopeUserId(): ?int;

    abstract public function showLeaderboard(): bool;

    public function getWidgetData(): array
    {
        return [
            'userId' => $this->scopeUserId(),
        ];
    }

    /**
     * Bypasses Filament's schema-based widget rendering (this page uses a
     * plain Blade view instead), so the data Filament would normally merge
     * in automatically - getWidgetData() plus pageFilters - is built here
     * for the view to pass into <x-filament-widgets::widgets> by hand.
     *
     * @return array<string, mixed>
     */
    public function widgetData(): array
    {
        return [
            ...$this->getWidgetData(),
            'pageFilters' => $this->filters,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function getStatsWidgets(): array
    {
        return [StatsOverview::class];
    }

    /**
     * @return array<int, class-string>
     */
    public function getChartWidgets(): array
    {
        return [
            TopPlacesChart::class,
            IncomeExpenseChart::class,
            SpendPerPlaceOverTimeChart::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function getTableWidgets(): array
    {
        return [
            SpendPerAccountTable::class,
            LargestTransactionsTable::class,
            RecurringChargesTable::class,
            ...($this->showLeaderboard() ? [LeaderboardTable::class] : []),
        ];
    }
}
