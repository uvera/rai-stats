<?php

namespace App\Filament\Pages;

use App\Enums\ReceiptProvider;
use App\Filament\Widgets\Groceries\BasketSizeOverTimeChart;
use App\Filament\Widgets\Groceries\GroceryStatsOverview;
use App\Filament\Widgets\Groceries\ProductCategorySpendChart;
use App\Filament\Widgets\Groceries\TopProductsChart;
use App\Models\GroceryAccount;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Standalone grocery analytics - deliberately not extending
 * AbstractStatsPage, so the product-level receipt charts stay in this
 * section and never appear on My Stats / Family Stats.
 */
class GroceryStats extends Page
{
    protected string $view = 'filament.pages.grocery-stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Groceries';

    protected static ?string $navigationLabel = 'Stats';

    protected static ?string $title = 'Grocery stats';

    protected static ?int $navigationSort = 4;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'groceryAccountId' => null,
            'provider' => null,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(4)
            ->components([
                DatePicker::make('from')->label('From')->native(false)->live(),
                DatePicker::make('to')->label('To')->native(false)->live(),
                Select::make('provider')
                    ->options(fn () => collect(ReceiptProvider::cases())
                        ->mapWithKeys(fn (ReceiptProvider $p) => [$p->value => $p->label()]))
                    ->placeholder('All providers')
                    ->live(),
                Select::make('groceryAccountId')
                    ->label('Account')
                    ->options(fn () => GroceryAccount::query()->orderBy('label')->pluck('label', 'id'))
                    ->placeholder('All accounts')
                    ->live(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetData(): array
    {
        return ['pageFilters' => $this->filters];
    }

    /**
     * @return array<int, class-string>
     */
    public function getStatsWidgets(): array
    {
        return [GroceryStatsOverview::class];
    }

    /**
     * @return array<int, class-string>
     */
    public function getChartWidgets(): array
    {
        return [
            ProductCategorySpendChart::class,
            TopProductsChart::class,
            BasketSizeOverTimeChart::class,
        ];
    }
}
