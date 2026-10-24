<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class BillsVersusRestChart extends ChartWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('Bills vs everything else', StatHints::BILLS);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $split = $this->stats()->billsVersusRest();

        return [
            'datasets' => [
                [
                    'label' => 'Bills',
                    'data' => array_map(fn (array $row) => $row['bills_cents'] / 100, $split),
                    'backgroundColor' => '#8b5cf6',
                ],
                [
                    'label' => 'Everything else',
                    'data' => array_map(fn (array $row) => $row['discretionary_cents'] / 100, $split),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => array_keys($split),
        ];
    }
}
