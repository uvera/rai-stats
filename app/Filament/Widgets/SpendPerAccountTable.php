<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsStatsFilters;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SpendPerAccountTable extends TableWidget
{
    use ReadsStatsFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->stats()->spendPerAccountQuery())
            ->heading('Spend per account')
            ->paginated(false)
            ->columns([
                Split::make([
                    TextColumn::make('description')
                        ->label('Account')
                        ->description(fn ($record) => $record->number)
                        ->sortable(),
                    Stack::make([
                        TextColumn::make('spend_cents')
                            ->label('Spend')
                            ->formatStateUsing(fn ($state, $record) => number_format(($state ?? 0) / 100, 2).' '.$record->currency_code)
                            ->color('danger')
                            ->sortable(),
                        TextColumn::make('income_cents')
                            ->label('Income')
                            ->formatStateUsing(fn ($state, $record) => number_format(($state ?? 0) / 100, 2).' '.$record->currency_code)
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
