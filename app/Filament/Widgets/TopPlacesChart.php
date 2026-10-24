<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class TopPlacesChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('Top places', StatHints::TOP_PLACES);
    }

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
