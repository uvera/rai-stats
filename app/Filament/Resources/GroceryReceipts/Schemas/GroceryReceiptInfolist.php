<?php

namespace App\Filament\Resources\GroceryReceipts\Schemas;

use App\Models\GroceryReceipt;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GroceryReceiptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('provider')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('store_name')->label('Store'),
                        TextEntry::make('store_address')->label('Address')->placeholder('—'),
                        TextEntry::make('store_format')->label('Format')->badge()->placeholder('—'),
                        TextEntry::make('purchased_at')->label('Purchased')->dateTime('d.m.Y H:i'),
                        TextEntry::make('total_cents')
                            ->label('Total')
                            ->formatStateUsing(fn (int $state, GroceryReceipt $record) => number_format($state / 100, 2).' '.$record->currency_code),
                        TextEntry::make('net_total_cents')
                            ->label('Net total')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?int $state, GroceryReceipt $record) => $state === null
                                ? null
                                : number_format($state / 100, 2).' '.$record->currency_code),
                        TextEntry::make('account.label')->label('Account')->badge()->color('gray'),
                    ]),

                Section::make('Fiscal')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pfr_number')->label('PFR receipt number')->placeholder('—')->copyable(),
                        TextEntry::make('purs_url')
                            ->label('Tax authority verification')
                            ->state(fn (GroceryReceipt $record) => $record->pursUrl())
                            ->placeholder('—')
                            ->url(fn (GroceryReceipt $record) => $record->pursUrl(), shouldOpenInNewTab: true)
                            ->limit(60),
                    ]),

                Section::make('Linked bank transaction')
                    ->schema([
                        TextEntry::make('transaction')
                            ->label('Transaction')
                            ->state(fn (GroceryReceipt $record) => $record->transaction
                                ? $record->transaction->date->format('d.m.Y').' · '.$record->transaction->place.' · '.number_format($record->transaction->amount_cents / 100, 2).' '.$record->transaction->currency_code
                                : null)
                            ->placeholder('Not linked — use “Link transaction”.'),
                        TextEntry::make('match_source')->label('Linked')->badge()->placeholder('—'),
                    ]),

                Section::make('Raw receipt text')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('raw_text')
                            ->hiddenLabel()
                            ->fontFamily('mono')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
