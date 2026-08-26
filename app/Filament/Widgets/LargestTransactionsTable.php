<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LargestTransactionsTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // No defaultSort(): the query is already ordered by ABS(amount_cents)
        // desc (largest first regardless of sign) via largestTransactionsQuery().
        // Setting a default sort here would make Filament order by the signed
        // amount instead, which isn't what "largest" means. Columns are still
        // individually sortable by clicking the header.
        return $table
            ->query(fn () => $this->stats()->largestTransactionsQuery())
            ->heading('Largest transactions')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('date')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('place')->searchable(),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2).' '.$record->currency_code)
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'success')
                    ->alignEnd()
                    ->sortable(),
            ]);
    }
}
