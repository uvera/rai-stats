<?php

namespace App\Filament\Resources\MaxiReceipts\Pages;

use App\Filament\Resources\MaxiReceipts\MaxiReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListMaxiReceipts extends ListRecords
{
    protected static string $resource = MaxiReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
