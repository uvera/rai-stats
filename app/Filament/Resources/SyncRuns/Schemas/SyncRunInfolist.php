<?php

namespace App\Filament\Resources\SyncRuns\Schemas;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SyncRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Run')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('kind')
                            ->label('Process')
                            ->badge()
                            ->formatStateUsing(fn (SyncKind $state) => $state->label()),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (SyncRunStatus $state) => $state->label())
                            ->color(fn (SyncRunStatus $state) => $state->color()),
                        TextEntry::make('started_at')->label('Started')->dateTime('d.m.Y H:i'),
                        TextEntry::make('finished_at')->label('Finished')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('user.name')->label('Started by')->placeholder('—'),
                        TextEntry::make('groceryAccount.label')
                            ->label('Account')
                            ->placeholder('—')
                            ->visible(fn (SyncRun $record) => $record->kind === SyncKind::GrocerySync),
                        TextEntry::make('summary')->columnSpanFull(),
                        TextEntry::make('message')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Receipts')
                    ->columns(2)
                    ->visible(fn (SyncRun $record) => $record->kind === SyncKind::GrocerySync && is_array($record->details))
                    ->schema([
                        TextEntry::make('imported')
                            ->label('New receipts')
                            ->state(fn (SyncRun $record) => $record->details['imported'] ?? null)
                            ->placeholder('—'),
                        TextEntry::make('total_invoices')
                            ->label('Invoices on the account')
                            ->state(fn (SyncRun $record) => $record->details['total_invoices'] ?? null)
                            ->placeholder('—'),
                    ]),

                Section::make('Accounts')
                    ->visible(fn (SyncRun $record) => $record->kind === SyncKind::BankImport && filled($record->details['accounts'] ?? null))
                    ->schema([
                        RepeatableEntry::make('accounts')
                            ->hiddenLabel()
                            ->state(fn (SyncRun $record) => $record->details['accounts'] ?? [])
                            ->table([
                                TableColumn::make('Account'),
                                TableColumn::make('Number'),
                                TableColumn::make('New'),
                                TableColumn::make('Result'),
                                TableColumn::make('Why'),
                            ])
                            ->schema([
                                TextEntry::make('description')->hiddenLabel(),
                                TextEntry::make('account_number')->hiddenLabel(),
                                TextEntry::make('inserted')->hiddenLabel(),
                                TextEntry::make('failed')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state) => $state ? 'Failed' : 'OK')
                                    ->color(fn (bool $state) => $state ? 'danger' : 'success'),
                                TextEntry::make('message')->hiddenLabel()->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
