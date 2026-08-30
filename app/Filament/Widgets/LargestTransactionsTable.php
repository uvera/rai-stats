<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LargestTransactionsTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->stats()->largestTransactionsQuery())
            ->heading('Largest transactions')
            ->paginated([10, 25, 50])
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('place')->searchable(),
                        TextColumn::make('date')->dateTime('d.m.Y')->sortable()->color('gray'),
                    ]),
                    TextColumn::make('amount_cents')
                        ->label('Amount')
                        ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2).' '.$record->currency_code)
                        ->color(fn (int $state) => $state < 0 ? 'danger' : 'success')
                        ->alignEnd()
                        ->grow(false)
                        // Sorted by ABS(amount_cents), not the signed value, so
                        // "largest" means biggest magnitude regardless of sign.
                        ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("ABS(amount_cents) {$direction}")),
                ]),
            ])
            ->defaultSort('amount_cents', 'desc');
    }
}
