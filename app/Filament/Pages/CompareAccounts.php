<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Compare\CompareSpendByCategoryTable;
use App\Filament\Widgets\Compare\CompareSpendOverTimeChart;
use App\Filament\Widgets\Compare\CompareSpendOverview;
use App\Models\Account;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompareAccounts extends Page
{
    protected string $view = 'filament.pages.compare-accounts';

    protected static ?string $navigationLabel = 'Compare';

    protected static ?string $title = 'Compare';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    public function mount(): void
    {
        $this->filters = [
            'accountA' => null,
            'accountB' => null,
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'period' => 'month',
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(4)
            ->components([
                Select::make('accountA')
                    ->label('Account A')
                    ->options(fn (): array => $this->accountOptions())
                    ->placeholder('Select an account')
                    ->required()
                    ->different('accountB')
                    ->disableOptionWhen(fn (mixed $value): bool => $this->isTheOtherAccount((string) $value, 'accountB'))
                    ->live(),
                Select::make('accountB')
                    ->label('Account B')
                    ->options(fn (): array => $this->accountOptions())
                    ->placeholder('Select an account')
                    ->required()
                    ->different('accountA')
                    ->disableOptionWhen(fn (mixed $value): bool => $this->isTheOtherAccount((string) $value, 'accountA'))
                    ->live(),
                DatePicker::make('from')
                    ->label('From')
                    ->native(false)
                    ->live(),
                DatePicker::make('to')
                    ->label('To')
                    ->native(false)
                    ->live()
                    ->afterOrEqual('from'),
                Select::make('period')
                    ->label('Period')
                    ->options([
                        'month' => 'Month',
                        'quarter' => 'Quarter',
                        'year' => 'Year',
                    ])
                    ->live(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetData(): array
    {
        return [
            'pageFilters' => $this->filters,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function getStatsWidgets(): array
    {
        return [CompareSpendOverview::class];
    }

    /**
     * @return array<int, class-string>
     */
    public function getChartWidgets(): array
    {
        return [CompareSpendOverTimeChart::class];
    }

    /**
     * @return array<int, class-string>
     */
    public function getTableWidgets(): array
    {
        return [CompareSpendByCategoryTable::class];
    }

    /**
     * @return array<int, string>
     */
    private function accountOptions(): array
    {
        return Account::query()
            ->with('user')
            ->orderBy('description')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [
                $account->id => "{$account->user->name} - {$account->description} ({$account->number})",
            ])
            ->all();
    }

    private function isTheOtherAccount(string $value, string $filterKey): bool
    {
        $other = $this->filters[$filterKey] ?? null;

        return filled($other) && (string) $value === (string) $other;
    }
}
