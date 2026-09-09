<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // One grouped query for all of the below - see TransactionStats::overview().
        $overview = $this->stats()->overview();

        return [
            Stat::make('Transactions', (string) $overview['transaction_count'])
                ->icon(Heroicon::OutlinedListBullet),
            ...$this->statsPerCurrency('Total income', $overview['income_cents'], Heroicon::OutlinedArrowTrendingUp, 'success'),
            ...$this->statsPerCurrency('Total expenses', $overview['expense_cents'], Heroicon::OutlinedArrowTrendingDown, 'danger'),
            ...$this->statsPerCurrency('Average spend', $overview['average_spend_cents'], Heroicon::OutlinedCalculator, 'danger'),
            ...$this->statsPerCurrency('ATM / cash withdrawals', $overview['atm_withdrawal_cents'], Heroicon::OutlinedBanknotes, 'warning'),
        ];
    }

    /**
     * One Stat per currency present in $totals, e.g. two rows for an
     * account holder with both EUR and RSD accounts. Currencies are sorted
     * alphabetically for a stable display order.
     *
     * @param  array<string, int>  $totalsByCurrency  currency_code => cents
     * @return array<int, Stat>
     */
    private function statsPerCurrency(string $label, array $totalsByCurrency, Heroicon $icon, string $color): array
    {
        ksort($totalsByCurrency);

        return collect($totalsByCurrency)
            ->map(fn (int $cents, string $currencyCode) => Stat::make(
                "{$label} ({$currencyCode})",
                number_format($cents / 100, 2),
            )->icon($icon)->color($color))
            ->values()
            ->all();
    }
}
