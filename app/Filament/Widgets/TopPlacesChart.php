<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Widgets\ChartWidget;

class TopPlacesChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected ?string $heading = 'Top places';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $places = $this->stats()->topPlaces();

        return [
            'datasets' => [[
                'label' => 'Spend',
                'data' => array_map(fn (array $p) => $p['spend_cents'] / 100, $places),
                'backgroundColor' => '#f59e0b',
            ]],
            'labels' => array_map(fn (array $p) => $p['place'], $places),
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
