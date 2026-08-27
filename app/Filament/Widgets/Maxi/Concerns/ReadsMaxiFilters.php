<?php

namespace App\Filament\Widgets\Maxi\Concerns;

use App\Support\MaxiReceiptStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Feeds the Moj Maxi stats widgets from the page's filter bar (date range +
 * optional account). Mirrors ReadsStatsFilters but builds a MaxiReceiptStats
 * so nothing here touches TransactionStats.
 */
trait ReadsMaxiFilters
{
    use InteractsWithPageFilters;

    protected function maxiStats(): MaxiReceiptStats
    {
        return new MaxiReceiptStats(
            from: CarbonImmutable::parse($this->pageFilters['from'] ?? now()->subMonths(6)->startOfMonth()),
            to: CarbonImmutable::parse($this->pageFilters['to'] ?? now()),
            maxiAccountId: filled($this->pageFilters['maxiAccountId'] ?? null)
                ? (int) $this->pageFilters['maxiAccountId']
                : null,
        );
    }
}
