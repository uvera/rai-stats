<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class WeekdaySpendChart extends ChartWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('Spend by weekday', StatHints::WEEKDAY_GROCERY);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = $this->groceryStats()->spendByWeekday();

        return [
            'datasets' => $this->spendDatasets($days),
            'labels' => array_map(fn (array $day) => $day['weekday'], $days),
        ];
    }

    /**
     * @param  array<int, array{spend_cents: array<string, int>}>  $days
     * @return array<int, array{label: string, data: array<int, float>, backgroundColor: string, borderColor: string}>
     */
    private function spendDatasets(array $days): array
    {
        $currencies = [];

        foreach ($days as $day) {
            foreach (array_keys($day['spend_cents']) as $currency) {
                $currencies[$currency] = true;
            }
        }

        $colors = ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6'];
        $datasets = [];
        $index = 0;

        foreach (array_keys($currencies) as $currency) {
            $color = $colors[$index % count($colors)];
            $datasets[] = [
                'label' => "Spend ({$currency})",
                'data' => array_map(fn (array $day) => ($day['spend_cents'][$currency] ?? 0) / 100, $days),
                'backgroundColor' => $color,
                'borderColor' => $color,
            ];
            $index++;
        }

        return $datasets;
    }
}
