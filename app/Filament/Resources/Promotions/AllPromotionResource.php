<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\ListAllPromotions;
use App\Filament\Resources\Promotions\Tables\AllPromotionsTable;
use App\Models\GroceryPromotion;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AllPromotionResource extends Resource
{
    protected static ?string $model = GroceryPromotion::class;

    protected static ?string $slug = 'promotions';

    protected static ?string $modelLabel = 'promotion';

    protected static ?string $pluralModelLabel = 'promotions';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationParentItem = 'Promotions';

    protected static ?string $navigationLabel = 'All Promotions';

    protected static ?int $navigationSort = 1;

    protected static bool $isGloballySearchable = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AllPromotionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('account');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllPromotions::route('/'),
        ];
    }
}
