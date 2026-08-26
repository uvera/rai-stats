<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
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
                TextColumn::make('place')->sortable(),
                TextColumn::make('months')->alignEnd()->sortable(),
                TextColumn::make('average_cents')
                    ->label('Avg amount')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->alignEnd()
                    ->sortable(),
            ]);
    }
}
