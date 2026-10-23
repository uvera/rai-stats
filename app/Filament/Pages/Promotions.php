<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Promotions\AllPromotionResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Sidebar parent for the promotion lists. The item itself opens All Promotions.
 */
class Promotions extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationLabel = 'Promotions';

    protected static ?string $title = 'Promotions';

    protected static ?int $navigationSort = 6;

    public static function getNavigationUrl(): string
    {
        return AllPromotionResource::getUrl('index');
    }

    public function mount(): void
    {
        $this->redirect(AllPromotionResource::getUrl('index'));
    }
}
