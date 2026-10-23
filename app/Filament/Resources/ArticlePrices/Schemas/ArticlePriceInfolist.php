<?php

namespace App\Filament\Resources\ArticlePrices\Schemas;

use App\Models\GroceryReceiptItem;
use App\Support\ArticlePrices;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticlePriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $loaded = null;

        $summary = function (GroceryReceiptItem $record) use (&$loaded): array {
            return $loaded ??= ArticlePrices::summary($record->name);
        };

        return $schema->components([
            Section::make('Unit price')
                ->columns(3)
                ->schema([
                    TextEntry::make('last_unit_price_cents')
                        ->label('Last')
                        ->state(fn (GroceryReceiptItem $record): int => $summary($record)['last_unit_price_cents'])
                        ->formatStateUsing(fn (int $state): string => ArticlePrices::money($state)),
                    TextEntry::make('min_unit_price_cents')
                        ->label('Min')
                        ->state(fn (GroceryReceiptItem $record): int => $summary($record)['min_unit_price_cents'])
                        ->formatStateUsing(fn (int $state): string => ArticlePrices::money($state)),
                    TextEntry::make('max_unit_price_cents')
                        ->label('Max')
                        ->state(fn (GroceryReceiptItem $record): int => $summary($record)['max_unit_price_cents'])
                        ->formatStateUsing(fn (int $state): string => ArticlePrices::money($state)),
                    TextEntry::make('avg_unit_price_cents')
                        ->label('Average')
                        ->state(fn (GroceryReceiptItem $record): int => $summary($record)['avg_unit_price_cents'])
                        ->formatStateUsing(fn (int $state): string => ArticlePrices::money($state)),
                    TextEntry::make('times_bought')
                        ->label('Times bought')
                        ->state(fn (GroceryReceiptItem $record): int => $summary($record)['times_bought']),
                    TextEntry::make('last_purchased_at')
                        ->label('Last purchased')
                        ->state(fn (GroceryReceiptItem $record): string => $summary($record)['last_purchased_at']->format('d.m.Y H:i')),
                ]),
        ]);
    }
}
