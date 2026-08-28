<?php

namespace App\Filament\Resources\GroceryAccounts\Pages;

use App\Filament\Resources\GroceryAccounts\GroceryAccountResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateGroceryAccount extends CreateRecord
{
    protected static string $resource = GroceryAccountResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Account added')
            ->body('Use “Sync receipts” to pull this account’s invoice history.')
            ->success()
            ->send();
    }
}
