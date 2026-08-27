<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Widgets\ChartWidget;

class SpendByCategoryChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected ?string $heading = 'Spend by category';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = $this->stats()->spendByCategory();

        return [
            'datasets' => [[
                'data' => array_map(fn (array $r) => $r['spend_cents'] / 100, $rows),
                'backgroundColor' => [
                    '#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6',
                    '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16',
                    '#06b6d4', '#a855f7', '#eab308', '#22c55e', '#f43f5e',
                    '#64748b',
                ],
            ]],
            'labels' => array_map(fn (array $r) => $r['category_name'], $rows),
        ];
    }
}
