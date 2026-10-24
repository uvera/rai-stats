<?php

namespace App\Filament\Resources\ProductCategories\RelationManagers;

use App\Filament\Resources\ArticlePrices\ArticlePriceResource;
use App\Models\GroceryReceiptItem;
use Carbon\CarbonImmutable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Articles';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultKeySort(false)
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
                ->selectRaw('MIN(grocery_receipt_items.id) as id')
                ->selectRaw('grocery_receipt_items.name as name')
                ->selectRaw('COUNT(*) as times_bought')
                ->selectRaw('MAX(grocery_receipts.purchased_at) as last_purchased_at')
                ->groupBy('grocery_receipt_items.name'))
            ->recordUrl(fn (GroceryReceiptItem $record): string => ArticlePriceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Article')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('times_bought')
                    ->label('Times bought')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_purchased_at')
                    ->label('Last purchased')
                    ->formatStateUsing(fn ($state) => CarbonImmutable::parse($state)->format('d.m.Y H:i'))
                    ->sortable(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
