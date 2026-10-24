<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class TopProductsChart extends ChartWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return HoverHint::text('Top products by spend', StatHints::TOP_PRODUCTS);
    }

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
            'labels' => array_map(fn (array $r) => $r['name'].' · '.$r['times_bought'].'× · '.$r['share'].'%', $rows),
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
