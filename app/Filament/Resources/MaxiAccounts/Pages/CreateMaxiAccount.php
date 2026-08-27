<?php

namespace App\Filament\Resources\MaxiAccounts\Pages;

use App\Filament\Resources\MaxiAccounts\MaxiAccountResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMaxiAccount extends CreateRecord
{
    protected static string $resource = MaxiAccountResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Account added')
            ->body('Use “Sync receipts” to pull this account’s invoice history.')
            ->success()
            ->send();
    }
}
