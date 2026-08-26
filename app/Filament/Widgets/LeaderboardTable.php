<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LeaderboardTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->stats()->leaderboardQuery())
            ->heading('Leaderboard')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Who')->sortable(),
                TextColumn::make('spend_cents')
                    ->label('Spend')
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2))
                    ->color('danger')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('income_cents')
                    ->label('Income')
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2))
                    ->color('success')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('spend_cents', 'desc');
    }
}
