<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\PeriodChange;
use App\Support\StatHints;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GroceryStatsOverview extends BaseWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $query = $this->groceryStats();
        $overview = $query->overview();
        $comparison = $query->periodComparison();
        $current = $comparison['current'];
        $previous = $comparison['previous'];
        $lastYear = $comparison['last_year'];

        return [
            $this->withChange(
                Stat::make('Receipts', (string) $overview['receipt_count'])
                    ->icon(Heroicon::OutlinedReceiptPercent),
                $current['receipt_count'],
                $previous['receipt_count'],
                $lastYear['receipt_count'],
                increaseIsBad: false,
            ),
            $this->withChange(
                Stat::make('Total spent (RSD)', number_format($overview['total_spent_cents'] / 100, 2))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('danger'),
                $current['total_spent_cents'],
                $previous['total_spent_cents'],
                $lastYear['total_spent_cents'],
                increaseIsBad: true,
            ),
            $this->withChange(
                Stat::make(HoverHint::text('Average basket (RSD)', StatHints::AVERAGE_BASKET), number_format($overview['average_basket_cents'] / 100, 2))
                    ->icon(Heroicon::OutlinedShoppingCart),
                $current['average_basket_cents'],
                $previous['average_basket_cents'],
                $lastYear['average_basket_cents'],
                increaseIsBad: true,
            ),
            Stat::make(HoverHint::text('VAT paid (RSD)', StatHints::VAT), number_format($overview['total_vat_cents'] / 100, 2))
                ->icon(Heroicon::OutlinedCalculator)
                ->color('warning'),
            Stat::make(HoverHint::text('Linked to transactions', StatHints::LINKED), $overview['linked_percentage'].'%')
                ->icon(Heroicon::OutlinedLink),
            ...$this->cardShareStats($query->cardSpendShare()),
        ];
    }

    /**
     * @param  array<string, array{linked_cents: int, expense_cents: int, share: int}>  $shares
     * @return array<int, Stat>
     */
    private function cardShareStats(array $shares): array
    {
        ksort($shares);

        $stats = [];

        foreach ($shares as $currency => $share) {
            $stats[] = Stat::make(HoverHint::text("Groceries on card ({$currency})", StatHints::CARD_SHARE), $share['share'].'%')
                ->description(number_format($share['linked_cents'] / 100, 2).' of '.number_format($share['expense_cents'] / 100, 2))
                ->icon(Heroicon::OutlinedLink);
        }

        return $stats;
    }

    private function withChange(Stat $stat, int $current, int $previous, int $lastYear, bool $increaseIsBad): Stat
    {
        $sentence = PeriodChange::sentence($current, $previous, $lastYear);

        if ($sentence === null) {
            return $stat;
        }

        $stat->description(PeriodChange::described($sentence, $this->groceryStats()->comparisonHint()));

        if ($previous === 0 || $current === $previous || ! $increaseIsBad) {
            return $stat;
        }

        $stat->descriptionColor($current > $previous ? 'danger' : 'success');

        return $stat;
    }
}
