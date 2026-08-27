<?php

namespace App\Filament\Widgets\Maxi;

use App\Filament\Widgets\Maxi\Concerns\ReadsMaxiFilters;
use Filament\Widgets\ChartWidget;

class TopProductsChart extends ChartWidget
{
    use ReadsMaxiFilters;

    protected ?string $heading = 'Top products by spend';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->maxiStats()->topProducts();

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
