<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Widgets\ChartWidget;

class SpendPerPlaceOverTimeChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected ?string $heading = 'Spend per place over time';

    private const COLORS = ['#6366f1', '#f59e0b', '#22c55e', '#ef4444', '#06b6d4'];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $stats = $this->stats();
        $matrix = $stats->spendPerPlaceOverTime();

        return [
            'datasets' => collect($matrix['places'])->values()->map(fn (array $row, int $i) => [
                'label' => $row['place'],
                'data' => array_map(fn (string $period) => ($row['totals'][$period] ?? 0) / 100, $matrix['periods']),
                'borderColor' => self::COLORS[$i % count(self::COLORS)],
                'backgroundColor' => self::COLORS[$i % count(self::COLORS)],
                'fill' => false,
            ])->all(),
            'labels' => array_map(fn (string $period) => $stats->formatPeriod($period), $matrix['periods']),
        ];
    }
}
