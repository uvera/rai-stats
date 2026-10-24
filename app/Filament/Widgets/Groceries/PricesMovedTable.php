<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\ArticlePrices;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PricesMovedTable extends TableWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->groceryStats()->pricesMovedQuery())
            ->heading(HoverHint::text('Prices that moved', StatHints::PRICES_MOVED))
            ->paginated(false)
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('name')->label('Product')->sortable(),
                TextColumn::make('times_bought')->label('Times')->alignEnd()->sortable(),
                TextColumn::make('avg_unit_price_cents')
                    ->label('Average')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_unit_price_cents')
                    ->label('Latest')
                    ->formatStateUsing(fn ($state) => ArticlePrices::money((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('raise_percent')
                    ->label('Above average')
                    ->formatStateUsing(fn ($state) => ArticlePrices::formatRaise((float) $state))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('raise_percent', 'desc');
    }
}
