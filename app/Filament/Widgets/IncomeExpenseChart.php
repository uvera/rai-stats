<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Widgets\ChartWidget;

class IncomeExpenseChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected ?string $heading = 'Income vs expense';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $stats = $this->stats();
        $trend = $stats->incomeVsExpenseTrend();

        return [
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => array_map(fn (array $r) => $r['income_cents'] / 100, $trend),
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Expense',
                    'data' => array_map(fn (array $r) => $r['expense_cents'] / 100, $trend),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => array_map(fn (array $r) => $stats->formatPeriod($r['period']), $trend),
        ];
    }
}
