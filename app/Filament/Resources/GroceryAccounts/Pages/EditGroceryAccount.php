<?php

namespace App\Filament\Resources\GroceryAccounts\Pages;

use App\Filament\Groceries\GrocerySyncAction;
use App\Filament\Resources\GroceryAccounts\GroceryAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGroceryAccount extends EditRecord
{
    protected static string $resource = GroceryAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GrocerySyncAction::sync(),
            GrocerySyncAction::signInAndSync(),
            DeleteAction::make(),
        ];
    }
}
