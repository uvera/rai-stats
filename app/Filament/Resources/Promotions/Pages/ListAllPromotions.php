<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Promotions\RefreshPromotionsAction;
use App\Filament\Resources\Promotions\AllPromotionResource;
use Filament\Resources\Pages\ListRecords;

class ListAllPromotions extends ListRecords
{
    protected static string $resource = AllPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RefreshPromotionsAction::make(),
        ];
    }
}
