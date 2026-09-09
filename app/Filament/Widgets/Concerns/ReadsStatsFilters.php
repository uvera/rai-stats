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
 * These widgets are kept off the Dashboard by not registering them for
 * discovery at all (see AdminPanelProvider) - not via canView(), which
 * Filament also checks on every Livewire hydration and would 403 the
 * stats page the moment a filter changed.
 */
trait ReadsStatsFilters
{
    use InteractsWithPageFilters;

    public ?int $userId = null;

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
