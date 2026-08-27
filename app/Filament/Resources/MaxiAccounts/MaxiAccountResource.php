<?php

namespace App\Filament\Resources\MaxiAccounts;

use App\Filament\Resources\MaxiAccounts\Pages\CreateMaxiAccount;
use App\Filament\Resources\MaxiAccounts\Pages\EditMaxiAccount;
use App\Filament\Resources\MaxiAccounts\Pages\ListMaxiAccounts;
use App\Filament\Resources\MaxiAccounts\Schemas\MaxiAccountForm;
use App\Filament\Resources\MaxiAccounts\Tables\MaxiAccountsTable;
use App\Models\MaxiAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MaxiAccountResource extends Resource
{
    protected static ?string $model = MaxiAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Moj Maxi';

    protected static ?string $navigationLabel = 'Maxi accounts';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MaxiAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaxiAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaxiAccounts::route('/'),
            'create' => CreateMaxiAccount::route('/create'),
            'edit' => EditMaxiAccount::route('/{record}/edit'),
        ];
    }
}
