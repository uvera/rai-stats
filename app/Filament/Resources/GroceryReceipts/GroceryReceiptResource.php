<?php

namespace App\Filament\Resources\GroceryReceipts;

use App\Filament\Resources\GroceryReceipts\Pages\ListGroceryReceipts;
use App\Filament\Resources\GroceryReceipts\Pages\ViewGroceryReceipt;
use App\Filament\Resources\GroceryReceipts\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\GroceryReceipts\Schemas\GroceryReceiptInfolist;
use App\Filament\Resources\GroceryReceipts\Tables\GroceryReceiptsTable;
use App\Models\GroceryReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GroceryReceiptResource extends Resource
{
    protected static ?string $model = GroceryReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationLabel = 'Receipts';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return GroceryReceiptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroceryReceiptsTable::configure($table);
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
            'index' => ListGroceryReceipts::route('/'),
            'view' => ViewGroceryReceipt::route('/{record}'),
        ];
    }
}
