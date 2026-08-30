<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecurringChargesTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->stats()->recurringChargesQuery())
            ->heading('Recurring charges')
            ->description('Same place, similar amount, 3+ months - a rough heuristic, not exact.')
            ->paginated(false)
            // The query groups by place with only a synthetic MIN(id) as the
            // row key, so Filament's usual "always tiebreak by the real,
            // qualified id column" default sort doesn't apply here - that
            // column isn't in the GROUP BY and Postgres rejects it.
            ->defaultKeySort(false)
            ->columns([
                Split::make([
                    TextColumn::make('place')->sortable(),
                    Stack::make([
                        TextColumn::make('average_cents')
                            ->label('Avg amount')
                            ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                            ->sortable(),
                        TextColumn::make('months')
                            ->formatStateUsing(fn ($state) => $state.' months')
                            ->color('gray')
                            ->sortable(),
                    ])
                        ->alignment('end')
                        ->grow(false),
                ]),
            ])
            ->defaultSort('months', 'desc');
    }
}
