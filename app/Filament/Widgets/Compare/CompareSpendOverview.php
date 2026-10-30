<?php

namespace App\Filament\Widgets\Compare;

use App\Filament\Widgets\Compare\Concerns\ReadsCompareFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CompareSpendOverview extends BaseWidget
{
    use ReadsCompareFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $comparison = $this->comparison();

        if ($comparison === null) {
            return [];
        }

        $totals = $comparison->totals();

        $stats = [
            $this->spendStat($this->accountLabel($comparison->accountA()), $totals['a']['spend_cents'], $totals['a']['currency_code']),
            $this->spendStat($this->accountLabel($comparison->accountB()), $totals['b']['spend_cents'], $totals['b']['currency_code']),
        ];

        $difference = $totals['difference_cents'];

        if ($comparison->sameCurrency() && $difference !== null) {
            $stats[] = Stat::make(
                'Difference ('.$totals['a']['currency_code'].')',
                $this->formatSpend($difference),
            )
                ->description($this->differenceDescription($this->accountLabel($comparison->accountA()), $this->accountLabel($comparison->accountB()), $difference))
                ->icon(Heroicon::OutlinedScale);
        }

        return $stats;
    }

    private function spendStat(string $description, int $cents, string $currencyCode): Stat
    {
        return Stat::make(
            "{$description} ({$currencyCode})",
            $this->formatSpend($cents),
        )
            ->icon(Heroicon::OutlinedArrowTrendingDown)
            ->color('danger');
    }

    private function differenceDescription(string $accountA, string $accountB, int $differenceCents): string
    {
        if ($differenceCents > 0) {
            return "{$accountA} spent more";
        }

        if ($differenceCents < 0) {
            return "{$accountB} spent more";
        }

        return 'Same spend';
    }
}
