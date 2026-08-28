<?php

namespace App\Filament\Resources\GroceryAccounts\Tables;

use App\Enums\ReceiptProvider;
use App\Filament\Groceries\GrocerySyncAction;
use App\Models\GroceryAccount;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroceryAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (ReceiptProvider $state) => $state->label()),
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
                    ->state(fn (GroceryAccount $record) => match (true) {
                        $record->tokenValid() => 'valid',
                        $record->canSyncUnattended() => 'auto',
                        filled($record->access_token) => 'expired',
                        default => 'none',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'valid' => 'success',
                        'auto' => 'info',
                        'expired' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options(fn () => collect(ReceiptProvider::cases())
                        ->mapWithKeys(fn (ReceiptProvider $p) => [$p->value => $p->label()])),
            ])
            ->recordActions([
                GrocerySyncAction::sync(),
                GrocerySyncAction::signInAndSync(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
