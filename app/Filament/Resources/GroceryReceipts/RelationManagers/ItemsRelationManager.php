<?php

namespace App\Filament\Resources\GroceryReceipts\RelationManagers;

use App\Enums\CategorySource;
use App\Models\GroceryReceiptItem;
use App\Models\ProductCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_category_id')
                ->label('Product category')
                ->options(fn () => ProductCategory::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Uncategorized'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('line_no')
            ->columns([
                TextColumn::make('line_no')->label('#')->sortable(),
                TextColumn::make('name')->label('Item')->wrap()->searchable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 3), '0'), '.'))
                    ->alignEnd(),
                TextColumn::make('unit_price_cents')
                    ->label('Unit')
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2))
                    ->alignEnd(),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('vat_label')->label('VAT')->badge()->placeholder('—'),
                TextColumn::make('productCategory.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Uncategorized'),
            ])
            ->recordActions([
                Action::make('categorize')
                    ->label('Categorize')
                    ->icon('heroicon-o-tag')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin())
                    ->form([
                        Select::make('product_category_id')
                            ->label('Product category')
                            ->options(fn () => ProductCategory::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Uncategorized'),
                    ])
                    ->fillForm(fn (GroceryReceiptItem $record) => ['product_category_id' => $record->product_category_id])
                    ->action(function (GroceryReceiptItem $record, array $data): void {
                        $record->update([
                            'product_category_id' => $data['product_category_id'] ?? null,
                            'category_source' => ($data['product_category_id'] ?? null) ? CategorySource::Manual : null,
                        ]);
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
