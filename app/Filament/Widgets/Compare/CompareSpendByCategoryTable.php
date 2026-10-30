<?php

namespace App\Filament\Widgets\Compare;

use App\Filament\Widgets\Compare\Concerns\ReadsCompareFilters;
use App\Support\AccountSpendComparison;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CompareSpendByCategoryTable extends TableWidget
{
    use ReadsCompareFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $comparison = $this->comparison();

        return $table
            ->stackedOnMobile()
            ->heading('Spend by category')
            ->records(fn (): array => $comparison?->byCategory() ?? [])
            ->paginated(false)
            ->columns($this->columns($comparison));
    }

    /**
     * @return array<int, TextColumn>
     */
    private function columns(?AccountSpendComparison $comparison): array
    {
        $columns = [
            TextColumn::make('category_name')
                ->label('Category'),
            $this->spendColumn('spend_a_cents', $comparison?->accountA()->description ?? 'Account A', $comparison?->accountA()->currency_code ?? ''),
            $this->spendColumn('spend_b_cents', $comparison?->accountB()->description ?? 'Account B', $comparison?->accountB()->currency_code ?? ''),
        ];

        if ($comparison?->sameCurrency()) {
            $currencyCode = $comparison->accountA()->currency_code;

            $columns[] = TextColumn::make('difference_cents')
                ->label('Difference')
                ->formatStateUsing(fn (int|string $state): string => $this->formatSpend((int) $state).' '.$currencyCode)
                ->alignEnd();
        }

        return $columns;
    }

    private function spendColumn(string $name, string $description, string $currencyCode): TextColumn
    {
        return TextColumn::make($name)
            ->label($description)
            ->formatStateUsing(fn (int|string $state): string => $this->formatSpend((int) $state).' '.$currencyCode)
            ->color('danger')
            ->alignEnd();
    }
}
