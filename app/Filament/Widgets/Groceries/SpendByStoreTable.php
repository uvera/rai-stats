<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SpendByStoreTable extends TableWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->groceryStats()->spendByStoreQuery())
            ->heading(HoverHint::text('Spend by store', StatHints::STORE))
            ->paginated(false)
            // Grouped by store, with a synthetic MIN(id) as the row key.
            // Filament's default id tiebreak is not in the GROUP BY.
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('store_name')->label('Store')->sortable(),
                TextColumn::make('store_format')->label('Format')->placeholder('—')->sortable(),
                TextColumn::make('receipts')->label('Trips')->alignEnd()->sortable(),
                TextColumn::make('spend_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('avg_basket_cents')
                    ->label('Avg basket')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('share')
                    ->label('Share')
                    ->formatStateUsing(fn ($state) => ((int) $state).'%')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('spend_cents', 'desc');
    }
}
