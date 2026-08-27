<?php

namespace App\Filament\Resources\MaxiAccounts\Pages;

use App\Filament\Resources\MaxiAccounts\MaxiAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaxiAccounts extends ListRecords
{
    protected static string $resource = MaxiAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
