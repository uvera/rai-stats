<?php

namespace App\Filament\Widgets\Groceries\Concerns;

use App\Enums\ReceiptProvider;
use App\Support\GroceryReceiptStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Feeds the grocery stats widgets from the page's filter bar (date range +
 * optional provider + optional account). Mirrors ReadsStatsFilters but builds
 * a GroceryReceiptStats so nothing here touches TransactionStats.
 */
trait ReadsGroceryFilters
{
    use InteractsWithPageFilters;

    protected function groceryStats(): GroceryReceiptStats
    {
        return new GroceryReceiptStats(
            from: CarbonImmutable::parse($this->pageFilters['from'] ?? now()->startOfYear()),
            to: CarbonImmutable::parse($this->pageFilters['to'] ?? now()),
            groceryAccountId: filled($this->pageFilters['groceryAccountId'] ?? null)
                ? (int) $this->pageFilters['groceryAccountId']
                : null,
            provider: filled($this->pageFilters['provider'] ?? null)
                ? ReceiptProvider::from($this->pageFilters['provider'])
                : null,
        );
    }
}
