<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StaplesTable extends TableWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->groceryStats()->staplesQuery())
            ->heading(HoverHint::text('Staples', StatHints::STAPLES))
            ->paginated(false)
            // Grouped by product name, with a synthetic MIN(id) as the row key.
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('name')->label('Product')->sortable(),
                TextColumn::make('months')->alignEnd()->sortable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 3), '0'), '.'))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('spend_cents')
                    ->label('Spend')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('months', 'desc');
    }
}
