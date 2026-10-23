<?php

namespace App\Filament\Resources\ArticlePrices;

use App\Filament\Resources\ArticlePrices\Pages\ListArticlePrices;
use App\Filament\Resources\ArticlePrices\Pages\ViewArticlePrice;
use App\Filament\Resources\ArticlePrices\Schemas\ArticlePriceInfolist;
use App\Filament\Resources\ArticlePrices\Tables\ArticlePricesTable;
use App\Models\GroceryReceiptItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ArticlePriceResource extends Resource
{
    protected static ?string $model = GroceryReceiptItem::class;

    protected static ?string $slug = 'article-prices';

    protected static ?string $modelLabel = 'article price';

    protected static ?string $pluralModelLabel = 'article prices';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationLabel = 'Article prices';

    protected static ?int $navigationSort = 3;

    protected static bool $isGloballySearchable = false;

    public static function canCreate(): bool
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

    public static function infolist(Schema $schema): Schema
    {
        return ArticlePriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlePricesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticlePrices::route('/'),
            'view' => ViewArticlePrice::route('/{record}'),
        ];
    }
}
