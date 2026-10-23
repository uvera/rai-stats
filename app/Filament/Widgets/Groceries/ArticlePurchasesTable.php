<?php

namespace App\Filament\Widgets\Groceries;

use App\Enums\ReceiptProvider;
use App\Filament\Resources\GroceryReceipts\GroceryReceiptResource;
use App\Models\GroceryReceiptItem;
use App\Support\ArticlePrices;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ArticlePurchasesTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public GroceryReceiptItem $record;

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->heading('Purchases')
            ->query(fn () => ArticlePrices::history($this->record->name))
            ->paginated([10, 25, 50])
            ->recordUrl(fn (GroceryReceiptItem $record): string => GroceryReceiptResource::getUrl('view', ['record' => $record->grocery_receipt_id]))
            ->columns([
                TextColumn::make('receipt.purchased_at')
                    ->label('Purchased')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('receipt.provider')
                    ->label('Provider')
                    ->badge()
                    ->formatStateUsing(fn (ReceiptProvider $state) => $state->label())
                    ->visibleFrom('md'),
                TextColumn::make('receipt.store_name')
                    ->label('Store'),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 3), '0'), '.'))
                    ->alignEnd()
                    ->visibleFrom('md'),
                TextColumn::make('unit_price_cents')
                    ->label('Unit')
                    ->formatStateUsing(fn (int $state) => ArticlePrices::money($state))
                    ->alignEnd(),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state) => ArticlePrices::money($state))
                    ->alignEnd(),
            ]);
    }
}
