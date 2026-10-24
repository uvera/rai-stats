<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class VatByRateChart extends ChartWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('VAT by rate', StatHints::VAT_BY_RATE);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->groceryStats()->vatByRate();

        return [
            'datasets' => [
                [
                    'label' => 'Spend (RSD)',
                    'data' => array_map(fn (array $row) => $row['spend_cents'] / 100, $rows),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'VAT (RSD)',
                    'data' => array_map(fn (array $row) => $row['vat_cents'] / 100, $rows),
                    'backgroundColor' => '#8b5cf6',
                ],
            ],
            'labels' => array_map(fn (array $row) => $row['rate'], $rows),
        ];
    }
}
