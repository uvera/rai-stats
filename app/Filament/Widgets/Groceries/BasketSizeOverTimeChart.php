<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class BasketSizeOverTimeChart extends ChartWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('Basket size over time', StatHints::BASKET_OVER_TIME);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = $this->groceryStats()->basketSizeOverTime();

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
