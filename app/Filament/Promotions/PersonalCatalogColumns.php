<?php

namespace App\Filament\Promotions;

use App\Models\GroceryPromotion;
use App\Models\GroceryPromotionMatch;
use App\Services\Promotions\PersonalCatalogComparison;
use App\Support\ArticlePrices;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PersonalCatalogColumns
{
    public static function filter(): SelectFilter
    {
        return SelectFilter::make('personal_vs_triweekly')
            ->label('Personal vs tri-weekly')
            ->options([
                'cheaper' => 'Cheaper than tri-weekly',
                'expensive' => 'More expensive than tri-weekly',
            ])
            ->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;

                if (! in_array($value, ['cheaper', 'expensive'], true)) {
                    return $query;
                }

                return PersonalCatalogComparison::constrain($query, $value);
            });
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('triweekly_price')
                ->label('Tri-weekly price')
                ->alignEnd()
                ->visible(fn (HasTable $livewire): bool => self::comparing($livewire))
                ->state(function (Model $record): ?string {
                    $cents = self::promotion($record)?->triweeklyGrossCents();

                    return $cents === null ? null : ArticlePrices::money($cents);
                }),
            TextColumn::make('triweekly_difference')
                ->label('Vs tri-weekly')
                ->alignEnd()
                ->visible(fn (HasTable $livewire): bool => self::comparing($livewire))
                ->state(function (Model $record): ?string {
                    $promotion = self::promotion($record);
                    $catalog = $promotion?->triweeklyGrossCents();

                    if ($promotion === null || $catalog === null) {
                        return null;
                    }

                    return ArticlePrices::money($promotion->gross_cents - $catalog);
                }),
        ];
    }

    private static function comparing(HasTable $livewire): bool
    {
        $state = $livewire->getTableFilterState('personal_vs_triweekly') ?? [];

        return filled($state['value'] ?? null);
    }

    private static function promotion(Model $record): ?GroceryPromotion
    {
        if ($record instanceof GroceryPromotion) {
            return $record;
        }

        if ($record instanceof GroceryPromotionMatch) {
            return $record->promotion;
        }

        return null;
    }
}
