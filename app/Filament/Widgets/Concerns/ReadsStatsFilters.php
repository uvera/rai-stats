<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\DateFilter;
use App\Support\TransactionStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Every stats widget needs the same two things from its page: the date
 * range/period filter state (via Filament's native page-filters mechanism)
 * and which user to scope to (passed separately through getWidgetData(),
 * since it's not something the user edits like the other filters).
 *
 * canView() here keeps these widgets off the discovered-widget Dashboard
 * (where they would render unscoped and unfiltered for every user) - they
 * are only ever composed onto the stats pages explicitly, via
 * <x-filament-widgets::widgets>, which renders by class and never consults
 * canView(). ($isLazy = false stays on each widget class: it can't live in
 * a trait, since the parent Widget already defines it.)
 */
trait ReadsStatsFilters
{
    use InteractsWithPageFilters;

    public ?int $userId = null;

    public static function canView(): bool
    {
        return false;
    }

    protected function stats(): TransactionStats
    {
        return new TransactionStats(
            userId: $this->userId,
            from: DateFilter::parseOr($this->pageFilters['from'] ?? null, CarbonImmutable::now()->startOfYear()),
            to: DateFilter::parseOr($this->pageFilters['to'] ?? null, CarbonImmutable::now()),
            period: in_array($this->pageFilters['period'] ?? null, ['month', 'quarter', 'year'], true)
                ? $this->pageFilters['period']
                : 'month',
            accountIds: filled($this->pageFilters['accountIds'] ?? null)
                ? array_map('intval', $this->pageFilters['accountIds'])
                : null,
        );
    }
}
