<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GroceryStatsOverview extends BaseWidget
{
    use ReadsGroceryFilters;

    protected function getStats(): array
    {
        $stats = $this->groceryStats();

        return [
            Stat::make('Receipts', (string) $stats->receiptCount())
                ->icon(Heroicon::OutlinedReceiptPercent),
            Stat::make('Total spent (RSD)', number_format($stats->totalSpentCents() / 100, 2))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('danger'),
            Stat::make('Average basket (RSD)', number_format($stats->averageBasketCents() / 100, 2))
                ->icon(Heroicon::OutlinedShoppingCart),
            Stat::make('VAT paid (RSD)', number_format($stats->totalVatCents() / 100, 2))
                ->icon(Heroicon::OutlinedCalculator)
                ->color('warning'),
            Stat::make('Linked to transactions', $stats->linkedPercentage().'%')
                ->icon(Heroicon::OutlinedLink),
        ];
    }
}
