<?php

namespace App\Filament\Resources\ArticlePrices\Tables;

use App\Enums\ReceiptProvider;
use App\Support\ArticlePrices;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticlePricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultKeySort(false)
            ->defaultSort('last_purchased_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Article')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('times_bought')
                    ->label('Times bought')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_unit_price_cents')
                    ->label('Last')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('min_unit_price_cents')
                    ->label('Min')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('max_unit_price_cents')
                    ->label('Max')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('avg_unit_price_cents')
                    ->label('Average')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('last_purchased_at')
                    ->label('Last purchased')
                    ->formatStateUsing(fn ($state) => CarbonImmutable::parse($state)->format('d.m.Y H:i'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options(fn () => collect(ReceiptProvider::cases())
                        ->mapWithKeys(fn (ReceiptProvider $provider) => [$provider->value => $provider->label()]))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('grocery_receipts.provider', $data['value'])
                        : $query),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
