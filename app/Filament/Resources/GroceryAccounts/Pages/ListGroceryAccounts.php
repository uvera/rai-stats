<?php

namespace App\Filament\Resources\GroceryAccounts\Pages;

use App\Filament\Resources\GroceryAccounts\GroceryAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGroceryAccounts extends ListRecords
{
    protected static string $resource = GroceryAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
