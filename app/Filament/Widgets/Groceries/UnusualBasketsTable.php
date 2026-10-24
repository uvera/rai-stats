<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UnusualBasketsTable extends TableWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->groceryStats()->unusualBasketsQuery())
            ->heading(HoverHint::text('Unusual for that store', StatHints::UNUSUAL_BASKET))
            ->paginated(false)
            ->columns([
                TextColumn::make('purchased_at')->label('Purchased')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('store_name')->label('Store')->placeholder('—')->sortable(),
                TextColumn::make('typical_cents')
                    ->label('Typical')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd(),
                TextColumn::make('total_cents')
                    ->label('Basket')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('purchased_at', 'desc');
    }
}
