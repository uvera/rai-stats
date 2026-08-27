<?php

namespace App\Filament\Resources\MaxiAccounts\Pages;

use App\Filament\Maxi\MaxiSyncAction;
use App\Filament\Resources\MaxiAccounts\MaxiAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaxiAccount extends EditRecord
{
    protected static string $resource = MaxiAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MaxiSyncAction::sync(),
            MaxiSyncAction::signInAndSync(),
            DeleteAction::make(),
        ];
    }
}
