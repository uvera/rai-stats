<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SavingsRateChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('Savings rate', StatHints::SAVINGS_RATE_CHART);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $stats = $this->stats();
        $trend = $stats->savingsRateTrend();
        $colors = ['#22c55e', '#3b82f6', '#f59e0b', '#8b5cf6'];
        $datasets = [];
        $index = 0;

        foreach ($trend['rates'] as $currency => $rates) {
            $color = $colors[$index % count($colors)];
            $datasets[] = [
                'label' => "Savings rate ({$currency})",
                'data' => $rates,
                'borderColor' => $color,
                'backgroundColor' => $color,
            ];
            $index++;
        }

        return [
            'datasets' => $datasets,
            'labels' => array_map(fn (string $period) => $stats->formatPeriod($period), $trend['periods']),
        ];
    }
}
