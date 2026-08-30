<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\UserStats;
use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use App\Models\User;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Split::make([
                    TextColumn::make('name')
                        ->label('Who')
                        ->sortable()
                        ->url(fn (User $record) => auth()->user()?->isAdmin()
                            ? UserStats::getUrl(['record' => $record])
                            : null),
                    Stack::make([
                        TextColumn::make('spend_cents')
                            ->label('Spend')
                            ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2))
                            ->color('danger')
                            ->sortable(),
                        TextColumn::make('income_cents')
                            ->label('Income')
                            ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2))
                            ->color('success')
                            ->sortable(),
                    ])
                        ->alignment('end')
                        ->grow(false),
                ]),
            ])
            ->defaultSort('spend_cents', 'desc');
    }
}
