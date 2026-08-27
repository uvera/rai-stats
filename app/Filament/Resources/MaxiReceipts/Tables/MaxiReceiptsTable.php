<?php

namespace App\Filament\Resources\MaxiReceipts\Tables;

use App\Models\MaxiAccount;
use App\Models\MaxiReceipt;
use App\Models\ProductCategory;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaxiReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('purchased_at', 'desc')
            ->columns([
                TextColumn::make('purchased_at')
                    ->label('Purchased')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('store_name')
                    ->label('Store')
                    ->searchable()
                    ->description(fn (MaxiReceipt $record) => $record->store_address),
                TextColumn::make('account.label')
                    ->label('Account')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state, MaxiReceipt $record) => number_format($state / 100, 2).' '.$record->currency_code)
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('transaction_id')
                    ->label('Linked')
                    ->boolean()
                    ->trueIcon('heroicon-o-link')
                    ->falseIcon('heroicon-o-minus')
                    ->state(fn (MaxiReceipt $record) => $record->transaction_id !== null),
                TextColumn::make('match_source')
                    ->label('Match')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('maxi_account_id')
                    ->label('Account')
                    ->options(fn () => MaxiAccount::query()->orderBy('label')->pluck('label', 'id')),
                SelectFilter::make('store_format')
                    ->label('Store format')
                    ->options(fn () => MaxiReceipt::query()
                        ->whereNotNull('store_format')
                        ->distinct()
                        ->orderBy('store_format')
                        ->pluck('store_format', 'store_format')),
                TernaryFilter::make('linked')
                    ->label('Linked to a transaction')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('transaction_id'),
                        false: fn (Builder $q) => $q->whereNull('transaction_id'),
                        blank: fn (Builder $q) => $q,
                    ),
                SelectFilter::make('product_category')
                    ->label('Contains product category')
                    ->options(fn () => ProductCategory::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('items', fn (Builder $i) => $i->where('product_category_id', $data['value']))
                        : $q),
                Filter::make('purchased_at')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('purchased_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('purchased_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
