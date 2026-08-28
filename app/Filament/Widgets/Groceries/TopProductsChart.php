<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use Filament\Widgets\ChartWidget;

class TopProductsChart extends ChartWidget
{
    use ReadsGroceryFilters;

    protected ?string $heading = 'Top products by spend';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->groceryStats()->topProducts();

        return [
            'datasets' => [[
                'label' => 'Spend (RSD)',
                'data' => array_map(fn (array $r) => $r['spend_cents'] / 100, $rows),
                'backgroundColor' => '#10b981',
            ]],
            'labels' => array_map(fn (array $r) => $r['name'], $rows),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
