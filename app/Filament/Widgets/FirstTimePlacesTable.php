<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FirstTimePlacesTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->stats()->firstTimePlacesQuery())
            ->heading(HoverHint::text('First time this period', StatHints::FIRST_PLACE))
            ->paginated(false)
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('place')->sortable(),
                TextColumn::make('currency_code')->label('Currency')->sortable(),
                TextColumn::make('first_date')->label('First seen')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('spend_cents')
                    ->label('Spend')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('spend_cents', 'desc');
    }
}
