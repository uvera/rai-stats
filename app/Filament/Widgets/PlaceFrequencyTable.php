<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PlaceFrequencyTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->stats()->placeFrequencyQuery())
            ->heading(HoverHint::text('How often vs how much', StatHints::PLACE_FREQUENCY))
            ->paginated(false)
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('place')->sortable(),
                TextColumn::make('currency_code')->label('Currency')->sortable(),
                TextColumn::make('charges')->alignEnd()->sortable(),
                TextColumn::make('average_cents')
                    ->label('Average')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('spend_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('charges', 'desc');
    }
}
