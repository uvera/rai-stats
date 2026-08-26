<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use ReadsStatsFilters;

    protected function getStats(): array
    {
        $stats = $this->stats();

        return [
            Stat::make('Transactions', (string) $stats->transactionCount())
                ->icon(Heroicon::OutlinedListBullet),
            ...$this->statsPerCurrency('Average spend', $stats->averageSpendByCurrency(), Heroicon::OutlinedCalculator, 'danger'),
            ...$this->statsPerCurrency('ATM / cash withdrawals', $stats->atmWithdrawalTotalsByCurrency(), Heroicon::OutlinedBanknotes, 'warning'),
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
