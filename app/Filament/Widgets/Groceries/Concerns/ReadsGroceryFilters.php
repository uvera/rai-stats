<?php

namespace App\Filament\Widgets\Groceries\Concerns;

use App\Enums\ReceiptProvider;
use App\Support\DateFilter;
use App\Support\GroceryReceiptStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Feeds the grocery stats widgets from the page's filter bar (date range +
 * optional provider + optional account). Mirrors ReadsStatsFilters,
 * including canView() to keep these off the Dashboard, but builds a
 * GroceryReceiptStats so nothing here touches TransactionStats.
 */
trait ReadsGroceryFilters
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return false;
    }

    protected function groceryStats(): GroceryReceiptStats
    {
        return new GroceryReceiptStats(
            from: DateFilter::parseOr($this->pageFilters['from'] ?? null, CarbonImmutable::now()->startOfYear()),
            to: DateFilter::parseOr($this->pageFilters['to'] ?? null, CarbonImmutable::now()),
            groceryAccountId: filled($this->pageFilters['groceryAccountId'] ?? null)
                ? (int) $this->pageFilters['groceryAccountId']
                : null,
            provider: filled($this->pageFilters['provider'] ?? null)
                ? ReceiptProvider::tryFrom($this->pageFilters['provider'])
                : null,
        );
    }
}
