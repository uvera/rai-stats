<?php

namespace App\Filament\Resources\SyncRuns\Tables;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Process')
                    ->badge()
                    ->formatStateUsing(fn (SyncKind $state) => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (SyncRunStatus $state) => $state->label())
                    ->color(fn (SyncRunStatus $state) => $state->color()),
                TextColumn::make('groceryAccount.label')
                    ->label('Account')
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('summary')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('message')
                    ->placeholder('—')
                    ->limit(80)
                    ->tooltip(fn (SyncRun $record) => $record->message)
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('user.name')
                    ->label('Started by')
                    ->placeholder('—')
                    ->visible(fn () => auth()->user()?->isAdmin()),
                TextColumn::make('finished_at')
                    ->label('Finished')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Process')
                    ->options(fn () => collect(SyncKind::cases())
                        ->mapWithKeys(fn (SyncKind $kind) => [$kind->value => $kind->label()])),
                SelectFilter::make('status')
                    ->options(fn () => collect(SyncRunStatus::cases())
                        ->mapWithKeys(fn (SyncRunStatus $status) => [$status->value => $status->label()])),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
