<?php

namespace App\Filament\Widgets\Maxi;

use App\Filament\Widgets\Maxi\Concerns\ReadsMaxiFilters;
use Filament\Widgets\ChartWidget;

class BasketSizeOverTimeChart extends ChartWidget
{
    use ReadsMaxiFilters;

    protected ?string $heading = 'Basket size over time';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = $this->maxiStats()->basketSizeOverTime();

        return [
            'datasets' => [
                [
                    'label' => 'Total spend (RSD)',
                    'data' => array_map(fn (array $r) => $r['spend_cents'] / 100, $rows),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Avg basket (RSD)',
                    'data' => array_map(fn (array $r) => $r['avg_basket_cents'] / 100, $rows),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#f59e0b',
                    'yAxisID' => 'y',
                ],
            ],
            'labels' => array_map(fn (array $r) => $r['period'], $rows),
        ];
    }
}
