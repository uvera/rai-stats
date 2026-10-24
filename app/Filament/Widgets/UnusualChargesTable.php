<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Support\HoverHint;
use App\Support\StatHints;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UnusualChargesTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn () => $this->stats()->unusualChargesQuery())
            ->heading(HoverHint::text('Unusual for that place', StatHints::UNUSUAL_CHARGE))
            ->paginated(false)
            ->columns([
                TextColumn::make('date')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('place')->sortable(),
                TextColumn::make('typical_cents')
                    ->label('Typical')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2))
                    ->alignEnd(),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => number_format(abs($state) / 100, 2).' '.$record->currency_code)
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc');
    }
}
