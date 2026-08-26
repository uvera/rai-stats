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
            Stat::make('Average spend', number_format($stats->averageSpendCents() / 100, 2))
                ->icon(Heroicon::OutlinedCalculator)
                ->color('danger'),
            Stat::make('ATM / cash withdrawals', number_format($stats->atmWithdrawalTotalCents() / 100, 2))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning'),
        ];
    }
}
