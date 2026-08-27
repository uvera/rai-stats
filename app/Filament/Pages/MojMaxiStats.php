<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Maxi\BasketSizeOverTimeChart;
use App\Filament\Widgets\Maxi\MaxiStatsOverview;
use App\Filament\Widgets\Maxi\ProductCategorySpendChart;
use App\Filament\Widgets\Maxi\TopProductsChart;
use App\Models\MaxiAccount;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Standalone Moj Maxi analytics - deliberately not extending
 * AbstractStatsPage, so the product-level receipt charts stay in this
 * section and never appear on My Stats / Family Stats.
 */
class MojMaxiStats extends Page
{
    protected string $view = 'filament.pages.moj-maxi-stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Moj Maxi';

    protected static ?string $navigationLabel = 'Stats';

    protected static ?string $title = 'Moj Maxi stats';

    protected static ?int $navigationSort = 4;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->subMonths(6)->startOfMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'maxiAccountId' => null,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(3)
            ->components([
                DatePicker::make('from')->label('From')->native(false)->live(),
                DatePicker::make('to')->label('To')->native(false)->live(),
                Select::make('maxiAccountId')
                    ->label('Maxi account')
                    ->options(fn () => MaxiAccount::query()->orderBy('label')->pluck('label', 'id'))
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
        return [MaxiStatsOverview::class];
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
