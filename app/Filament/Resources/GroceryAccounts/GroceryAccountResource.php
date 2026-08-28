<?php

namespace App\Filament\Resources\GroceryAccounts;

use App\Filament\Resources\GroceryAccounts\Pages\CreateGroceryAccount;
use App\Filament\Resources\GroceryAccounts\Pages\EditGroceryAccount;
use App\Filament\Resources\GroceryAccounts\Pages\ListGroceryAccounts;
use App\Filament\Resources\GroceryAccounts\Schemas\GroceryAccountForm;
use App\Filament\Resources\GroceryAccounts\Tables\GroceryAccountsTable;
use App\Models\GroceryAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GroceryAccountResource extends Resource
{
    protected static ?string $model = GroceryAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return GroceryAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroceryAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroceryAccounts::route('/'),
            'create' => CreateGroceryAccount::route('/create'),
            'edit' => EditGroceryAccount::route('/{record}/edit'),
        ];
    }
}
