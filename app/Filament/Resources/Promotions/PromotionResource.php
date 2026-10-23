<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Tables\PromotionsTable;
use App\Models\GroceryPromotionMatch;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PromotionResource extends Resource
{
    protected static ?string $model = GroceryPromotionMatch::class;

    protected static ?string $slug = 'matching-promotions';

    protected static ?string $modelLabel = 'matching promotion';

    protected static ?string $pluralModelLabel = 'matching promotions';

    protected static ?string $recordTitleAttribute = 'article_name';

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationParentItem = 'Promotions';

    protected static ?string $navigationLabel = 'Matching Promotions';

    protected static ?int $navigationSort = 2;

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
        return PromotionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('grocery_promotion_matches.*')
            ->join('grocery_promotions', 'grocery_promotions.id', '=', 'grocery_promotion_matches.grocery_promotion_id')
            ->with(['promotion.account']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
        ];
    }
}
