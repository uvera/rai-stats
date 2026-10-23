<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Enums\ReceiptProvider;
use App\Filament\Promotions\PersonalCatalogColumns;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use App\Support\ArticlePrices;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllPromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultKeySort(false)
            ->recordUrl(null)
            ->defaultSort(fn (Builder $query): Builder => self::orderByDiscount($query, 'desc'))
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->checkFileExistence(false)
                    ->height(40),
                TextColumn::make('name')
                    ->label('Promotion')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('brand')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('gross_cents')
                    ->label('Promo price')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('strike_gross_cents')
                    ->label('Was')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByDiscount($query, $direction))
                    ->state(function (GroceryPromotion $record): ?string {
                        $strike = $record->strike_gross_cents;
                        $gross = $record->gross_cents;

                        if ($strike === null || $strike <= 0) {
                            return null;
                        }

                        return number_format(($strike - $gross) / $strike * 100, 1).'%';
                    }),
                TextColumn::make('ends_on')
                    ->label('Valid until')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('account.label')
                    ->label('Account')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                ...PersonalCatalogColumns::columns(),
            ])
            ->filters([
                PersonalCatalogColumns::filter(),
                SelectFilter::make('account')
                    ->label('Account')
                    ->options(fn () => GroceryAccount::query()
                        ->whereIn('provider', [ReceiptProvider::Metro, ReceiptProvider::Maxi])
                        ->orderBy('label')
                        ->pluck('label', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('grocery_account_id', $data['value'])
                        : $query),
            ]);
    }

    private static function orderByDiscount(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw(
            "CASE WHEN strike_gross_cents > 0 THEN (strike_gross_cents - gross_cents)::float / strike_gross_cents ELSE 0 END {$direction}"
        );
    }
}
