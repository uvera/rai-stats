<?php

namespace App\Filament\Resources\ArticlePrices\Pages;

use App\Filament\Resources\ArticlePrices\ArticlePriceResource;
use App\Filament\Widgets\Groceries\ArticlePriceHistoryChart;
use App\Filament\Widgets\Groceries\ArticlePurchasesTable;
use App\Models\GroceryReceiptItem;
use Filament\Resources\Pages\ViewRecord;

class ViewArticlePrice extends ViewRecord
{
    protected static string $resource = ArticlePriceResource::class;

    public function getTitle(): string
    {
        /** @var GroceryReceiptItem $record */
        $record = $this->getRecord();

        return $record->name;
    }

    /**
     * @return array<int, class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            ArticlePriceHistoryChart::class,
            ArticlePurchasesTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
