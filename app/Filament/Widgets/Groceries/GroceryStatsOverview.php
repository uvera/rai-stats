<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GroceryStatsOverview extends BaseWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // One grouped query for receipts + one for VAT - see GroceryReceiptStats::overview().
        $overview = $this->groceryStats()->overview();

        return [
            Stat::make('Receipts', (string) $overview['receipt_count'])
                ->icon(Heroicon::OutlinedReceiptPercent),
            Stat::make('Total spent (RSD)', number_format($overview['total_spent_cents'] / 100, 2))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('danger'),
            Stat::make('Average basket (RSD)', number_format($overview['average_basket_cents'] / 100, 2))
                ->icon(Heroicon::OutlinedShoppingCart),
            Stat::make('VAT paid (RSD)', number_format($overview['total_vat_cents'] / 100, 2))
                ->icon(Heroicon::OutlinedCalculator)
                ->color('warning'),
            Stat::make('Linked to transactions', $overview['linked_percentage'].'%')
                ->icon(Heroicon::OutlinedLink),
        ];
    }
}
