<?php

namespace App\Filament\Widgets\Compare;

use App\Filament\Widgets\Compare\Concerns\ReadsCompareFilters;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class CompareSpendOverTimeChart extends ChartWidget
{
    use ReadsCompareFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Spend over time';

    public function getDescription(): string|Htmlable|null
    {
        $comparison = $this->comparison();

        if ($comparison === null || $comparison->sameCurrency()) {
            return null;
        }

        $currencyA = $comparison->accountA()->currency_code;
        $currencyB = $comparison->accountB()->currency_code;

        return "Amounts are in different currencies ({$currencyA} and {$currencyB}).";
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $comparison = $this->comparison();

        if ($comparison === null) {
            return [];
        }

        $rows = $comparison->overTime();

        return [
            'datasets' => [
                [
                    'label' => $comparison->accountA()->description,
                    'data' => array_map(fn (array $row): float => $row['spend_a_cents'] / 100, $rows),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => $comparison->accountB()->description,
                    'data' => array_map(fn (array $row): float => $row['spend_b_cents'] / 100, $rows),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => array_map(fn (array $row): string => $row['label'], $rows),
        ];
    }
}
