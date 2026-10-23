<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Enums\ReceiptProvider;
use App\Filament\Promotions\PersonalCatalogColumns;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotionMatch;
use App\Support\ArticlePrices;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultKeySort(false)
            ->recordUrl(null)
            ->defaultSort(fn (Builder $query): Builder => self::orderByDiscount($query, 'desc')
                ->orderByDesc('grocery_promotion_matches.times_bought'))
            ->columns([
                ImageColumn::make('promotion.image_url')
                    ->label('Image')
                    ->checkFileExistence(false)
                    ->height(40),
                TextColumn::make('promotion.name')
                    ->label('Promotion')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('article_name')
                    ->label('Bought as')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('times_bought')
                    ->label('Times bought')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_unit_price_cents')
                    ->label('Last paid')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('promotion.gross_cents')
                    ->label('Promo price')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('promotion.strike_gross_cents')
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
                    ->state(function (GroceryPromotionMatch $record): ?string {
                        $strike = $record->promotion?->strike_gross_cents;
                        $gross = $record->promotion?->gross_cents;

                        if ($strike === null || $strike <= 0 || $gross === null) {
                            return null;
                        }

                        return number_format(($strike - $gross) / $strike * 100, 1).'%';
                    }),
                TextColumn::make('promotion.ends_on')
                    ->label('Valid until')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('promotion.account.label')
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
                        ? $query->where('grocery_promotions.grocery_account_id', $data['value'])
                        : $query),
            ]);
    }

    private static function orderByDiscount(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw(
            "CASE WHEN grocery_promotions.strike_gross_cents > 0 THEN (grocery_promotions.strike_gross_cents - grocery_promotions.gross_cents)::float / grocery_promotions.strike_gross_cents ELSE 0 END {$direction}"
        );
    }
}
