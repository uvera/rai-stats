<?php

namespace App\Filament\Resources\MaxiAccounts\Tables;

use App\Filament\Maxi\MaxiSyncAction;
use App\Models\MaxiAccount;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaxiAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('user.name')
                    ->label('Matches')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('receipts_count')
                    ->label('Receipts')
                    ->counts('receipts'),
                TextColumn::make('token_status')
                    ->label('Token')
                    ->badge()
                    ->state(fn (MaxiAccount $record) => match (true) {
                        $record->tokenValid() => 'valid',
                        filled($record->access_token) => 'expired',
                        default => 'none',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'valid' => 'success',
                        'expired' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->recordActions([
                MaxiSyncAction::sync(),
                MaxiSyncAction::signInAndSync(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
