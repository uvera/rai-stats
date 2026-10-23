<?php

namespace App\Filament\Resources\ArticlePrices\Pages;

use App\Filament\Resources\ArticlePrices\ArticlePriceResource;
use App\Support\ArticlePrices;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListArticlePrices extends ListRecords
{
    protected static string $resource = ArticlePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return ArticlePrices::aggregateQuery();
    }
}
