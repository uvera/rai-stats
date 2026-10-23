<?php

namespace App\Filament\Widgets\Groceries;

use App\Models\GroceryReceiptItem;
use App\Support\ArticlePrices;
use Filament\Widgets\ChartWidget;

class ArticlePriceHistoryChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Unit price over time';

    protected int|string|array $columnSpan = 'full';

    public GroceryReceiptItem $record;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $lines = ArticlePrices::history($this->record->name)
            ->reorder()
            ->orderBy('grocery_receipts.purchased_at')
            ->orderBy('grocery_receipt_items.id')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Unit price (RSD)',
                'data' => $lines->map(fn (GroceryReceiptItem $line) => $line->unit_price_cents / 100)->all(),
                'borderColor' => '#10b981',
                'backgroundColor' => '#10b981',
            ]],
            'labels' => $lines->map(fn (GroceryReceiptItem $line) => $line->receipt->purchased_at->format('d.m.Y'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
