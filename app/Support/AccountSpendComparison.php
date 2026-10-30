<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Spending for two bank accounts over one date range, kept side by side.
 * Spend is negative amounts only. Reserved rows are excluded.
 */
class AccountSpendComparison
{
    /**
     * @param  'month'|'quarter'|'year'  $period
     */
    public function __construct(
        private Account $accountA,
        private Account $accountB,
        private CarbonImmutable $from,
        private CarbonImmutable $to,
        private string $period = 'month',
    ) {}

    public function accountA(): Account
    {
        return $this->accountA;
    }

    public function accountB(): Account
    {
        return $this->accountB;
    }

    public function sameCurrency(): bool
    {
        return $this->accountA->currency_code === $this->accountB->currency_code;
    }

    /**
     * Totals are the sums of the category rows, so a category that only one
     * account used still counts toward that account.
     *
     * @return array{
     *     a: array{spend_cents: int, currency_code: string},
     *     b: array{spend_cents: int, currency_code: string},
     *     difference_cents: int|null
     * }
     */
    public function totals(): array
    {
        $rows = $this->byCategory();

        $spendA = (int) array_sum(array_column($rows, 'spend_a_cents'));
        $spendB = (int) array_sum(array_column($rows, 'spend_b_cents'));

        return [
            'a' => [
                'spend_cents' => $spendA,
                'currency_code' => $this->accountA->currency_code,
            ],
            'b' => [
                'spend_cents' => $spendB,
                'currency_code' => $this->accountB->currency_code,
            ],
            'difference_cents' => $this->difference($spendA, $spendB),
        ];
    }

    /**
     * One row per category name. A side with no spend in that category is 0.
     * Ordered by the larger of the two spends, descending.
     *
     * @return array<int, array{category_name: string, spend_a_cents: int, spend_b_cents: int, difference_cents: int|null}>
     */
    public function byCategory(): array
    {
        $rows = $this->baseQuery()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->groupBy('transactions.category_id', 'categories.name', 'transactions.account_id')
            ->selectRaw("transactions.account_id, COALESCE(categories.name, 'Uncategorized') as category_name")
            ->selectRaw('SUM(-transactions.amount_cents) as spend_cents')
            ->get();

        $pivoted = [];

        foreach ($this->bucketSpend($rows, 'category_name') as $categoryName => $spend) {
            $pivoted[] = [
                'category_name' => $categoryName,
                'spend_a_cents' => $spend['spend_a_cents'],
                'spend_b_cents' => $spend['spend_b_cents'],
                'difference_cents' => $this->difference($spend['spend_a_cents'], $spend['spend_b_cents']),
            ];
        }

        usort($pivoted, function (array $left, array $right): int {
            $bySpend = max($right['spend_a_cents'], $right['spend_b_cents']) <=> max($left['spend_a_cents'], $left['spend_b_cents']);

            if ($bySpend !== 0) {
                return $bySpend;
            }

            return $left['category_name'] <=> $right['category_name'];
        });

        return $pivoted;
    }

    /**
     * One row per period bucket that either account spent in. The other
     * account is 0 for that bucket. Labels match My Stats / Family Stats.
     *
     * @return array<int, array{period: string, label: string, spend_a_cents: int, spend_b_cents: int}>
     */
    public function overTime(): array
    {
        $rows = $this->baseQuery()
            ->groupByRaw($this->periodTruncSql().', transactions.account_id')
            ->selectRaw($this->periodTruncSql().' as period')
            ->selectRaw('transactions.account_id')
            ->selectRaw('SUM(-transactions.amount_cents) as spend_cents')
            ->orderBy('period')
            ->get();

        $pivoted = $this->bucketSpend($rows, 'period');
        ksort($pivoted);

        $ordered = [];

        foreach ($pivoted as $period => $spend) {
            $ordered[] = [
                'period' => $period,
                'label' => $this->formatPeriod($period),
                'spend_a_cents' => $spend['spend_a_cents'],
                'spend_b_cents' => $spend['spend_b_cents'],
            ];
        }

        return $ordered;
    }

    private function difference(int $spendA, int $spendB): ?int
    {
        if (! $this->sameCurrency()) {
            return null;
        }

        return $spendA - $spendB;
    }

    private function baseQuery(): Builder
    {
        return Transaction::query()
            ->excludingReserved()
            ->whereIn('transactions.account_id', [$this->accountA->id, $this->accountB->id])
            ->whereBetween('transactions.date', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->where('transactions.amount_cents', '<', 0);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array{spend_a_cents: int, spend_b_cents: int}>
     */
    private function bucketSpend(Collection $rows, string $key): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $bucket = (string) $row->{$key};
            $buckets[$bucket] ??= ['spend_a_cents' => 0, 'spend_b_cents' => 0];

            $side = (int) $row->account_id === $this->accountA->id ? 'spend_a_cents' : 'spend_b_cents';
            $buckets[$bucket][$side] += (int) $row->spend_cents;
        }

        return $buckets;
    }

    private function formatPeriod(string $period): string
    {
        $date = CarbonImmutable::parse($period);

        return match ($this->period) {
            'quarter' => 'Q'.$date->quarter.' '.$date->year,
            'year' => (string) $date->year,
            default => $date->format('M Y'),
        };
    }

    /**
     * The match is the injection guard: $this->period comes from page
     * filters and must never be interpolated into date_trunc() directly.
     */
    private function periodTruncSql(): string
    {
        $unit = match ($this->period) {
            'quarter' => 'quarter',
            'year' => 'year',
            default => 'month',
        };

        return "date_trunc('{$unit}', transactions.date)";
    }
}
