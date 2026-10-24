<?php

namespace App\Filament\Widgets\Groceries;

use App\Filament\Widgets\Groceries\Concerns\ReadsGroceryFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FirstTimeProductsTable extends TableWidget
{
    use ReadsGroceryFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->groceryStats()->firstTimeProductsQuery())
            ->heading(HoverHint::text('First time this period', StatHints::FIRST_PRODUCT))
            ->paginated(false)
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('name')->label('Product')->sortable(),
                TextColumn::make('first_purchased_at')->label('First bought')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('times_bought')->label('Times')->alignEnd()->sortable(),
                TextColumn::make('spend_cents')
                    ->label('Spend')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('spend_cents', 'desc');
    }
}
