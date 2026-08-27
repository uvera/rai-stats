<?php

namespace App\Filament\Resources\MaxiReceipts;

use App\Filament\Resources\MaxiReceipts\Pages\ListMaxiReceipts;
use App\Filament\Resources\MaxiReceipts\Pages\ViewMaxiReceipt;
use App\Filament\Resources\MaxiReceipts\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\MaxiReceipts\Schemas\MaxiReceiptInfolist;
use App\Filament\Resources\MaxiReceipts\Tables\MaxiReceiptsTable;
use App\Models\MaxiReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MaxiReceiptResource extends Resource
{
    protected static ?string $model = MaxiReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Moj Maxi';

    protected static ?string $navigationLabel = 'Receipts';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaxiReceiptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaxiReceiptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaxiReceipts::route('/'),
            'view' => ViewMaxiReceipt::route('/{record}'),
        ];
    }
}
