<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\TransactionStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Every stats widget needs the same two things from its page: the date
 * range/period filter state (via Filament's native page-filters mechanism)
 * and which user to scope to (passed separately through getWidgetData(),
 * since it's not something the user edits like the other filters).
 */
trait ReadsStatsFilters
{
    use InteractsWithPageFilters;

    public ?int $userId = null;

    protected function stats(): TransactionStats
    {
        return new TransactionStats(
            userId: $this->userId,
            from: CarbonImmutable::parse($this->pageFilters['from'] ?? now()->startOfYear()),
            to: CarbonImmutable::parse($this->pageFilters['to'] ?? now()),
            period: $this->pageFilters['period'] ?? 'month',
            accountIds: filled($this->pageFilters['accountIds'] ?? null)
                ? array_map('intval', $this->pageFilters['accountIds'])
                : null,
        );
    }
}
