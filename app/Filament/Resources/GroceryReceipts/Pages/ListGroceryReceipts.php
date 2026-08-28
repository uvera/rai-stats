<?php

namespace App\Filament\Resources\GroceryReceipts\Pages;

use App\Filament\Resources\GroceryReceipts\GroceryReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListGroceryReceipts extends ListRecords
{
    protected static string $resource = GroceryReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
