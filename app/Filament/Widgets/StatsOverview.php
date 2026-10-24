<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\PeriodChange;
use App\Support\StatHints;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverview extends BaseWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $query = $this->stats();
        $overview = $query->overview();
        $comparison = $query->periodComparison();

        return [
            Stat::make(HoverHint::text('Transactions', StatHints::TRANSACTIONS), (string) $overview['transaction_count'])
                ->icon(Heroicon::OutlinedListBullet),
            ...$this->flowStats('Total income', $overview['income_cents'], 'income_cents', $comparison, Heroicon::OutlinedArrowTrendingUp, 'success', increaseIsGood: true),
            ...$this->flowStats('Total expenses', $overview['expense_cents'], 'expense_cents', $comparison, Heroicon::OutlinedArrowTrendingDown, 'danger', increaseIsGood: false),
            ...$this->netStats($comparison),
            ...$this->statsPerCurrency('Average spend', $overview['average_spend_cents'], Heroicon::OutlinedCalculator, 'danger', StatHints::AVERAGE_SPEND),
            ...$this->statsPerCurrency('ATM / cash withdrawals', $overview['atm_withdrawal_cents'], Heroicon::OutlinedBanknotes, 'warning', StatHints::ATM),
            ...$this->savingsStats($query->savingsRateByCurrency()),
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
    private function statsPerCurrency(string $label, array $totalsByCurrency, Heroicon $icon, string $color, ?string $hint = null): array
    {
        ksort($totalsByCurrency);

        return collect($totalsByCurrency)
            ->map(fn (int $cents, string $currencyCode) => Stat::make(
                $this->label("{$label} ({$currencyCode})", $hint),
                number_format($cents / 100, 2),
            )->icon($icon)->color($color))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $totalsByCurrency
     * @param  array{
     *     current: array<string, array{income_cents: int, expense_cents: int, net_cents: int}>,
     *     previous: array<string, array{income_cents: int, expense_cents: int, net_cents: int}>,
     *     last_year: array<string, array{income_cents: int, expense_cents: int, net_cents: int}>,
     * }  $comparison
     * @return array<int, Stat>
     */
    private function flowStats(
        string $label,
        array $totalsByCurrency,
        string $metric,
        array $comparison,
        Heroicon $icon,
        string $color,
        bool $increaseIsGood,
    ): array {
        ksort($totalsByCurrency);

        $stats = [];

        foreach ($totalsByCurrency as $currency => $cents) {
            $stat = Stat::make($this->label("{$label} ({$currency})"), number_format($cents / 100, 2))
                ->icon($icon)
                ->color($color);

            $this->describeChange(
                $stat,
                $cents,
                $comparison['previous'][$currency][$metric] ?? 0,
                $comparison['last_year'][$currency][$metric] ?? 0,
                $increaseIsGood,
            );

            $stats[] = $stat;
        }

        return $stats;
    }

    /**
     * @param  array{
     *     current: array<string, array{income_cents: int, expense_cents: int, net_cents: int}>,
     *     previous: array<string, array{income_cents: int, expense_cents: int, net_cents: int}>,
     *     last_year: array<string, array{income_cents: int, expense_cents: int, net_cents: int}>,
     * }  $comparison
     * @return array<int, Stat>
     */
    private function netStats(array $comparison): array
    {
        $current = $comparison['current'];
        ksort($current);

        $stats = [];

        foreach ($current as $currency => $flow) {
            if ($flow['income_cents'] === 0 && $flow['expense_cents'] === 0) {
                continue;
            }

            $stat = Stat::make(HoverHint::text("Net ({$currency})", StatHints::NET), number_format($flow['net_cents'] / 100, 2))
                ->icon(Heroicon::OutlinedCalculator)
                ->color($flow['net_cents'] >= 0 ? 'success' : 'danger');

            $this->describeChange(
                $stat,
                $flow['net_cents'],
                $comparison['previous'][$currency]['net_cents'] ?? 0,
                $comparison['last_year'][$currency]['net_cents'] ?? 0,
                increaseIsGood: true,
            );

            $stats[] = $stat;
        }

        return $stats;
    }

    /**
     * @param  array<string, int>  $rates
     * @return array<int, Stat>
     */
    private function savingsStats(array $rates): array
    {
        ksort($rates);

        $stats = [];

        foreach ($rates as $currency => $rate) {
            $stats[] = Stat::make(HoverHint::text("Savings rate ({$currency})", StatHints::SAVINGS_RATE), $rate.'%')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color($rate >= 0 ? 'success' : 'danger');
        }

        return $stats;
    }

    private function describeChange(Stat $stat, int $current, int $previous, int $lastYear, bool $increaseIsGood): void
    {
        $sentence = PeriodChange::sentence($current, $previous, $lastYear);

        if ($sentence === null) {
            return;
        }

        $stat->description(PeriodChange::described($sentence, $this->stats()->comparisonHint()));

        if ($previous === 0 || $current === $previous) {
            return;
        }

        $increased = $current > $previous;
        $stat->descriptionColor(($increased === $increaseIsGood) ? 'success' : 'danger');
    }

    private function label(string $label, ?string $hint = null): string|HtmlString
    {
        return $hint === null ? $label : HoverHint::text($label, $hint);
    }
}
