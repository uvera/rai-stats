<?php

namespace App\Support;

use App\Enums\ReceiptProvider;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query layer behind the grocery stats page - kept separate from
 * TransactionStats on purpose, so the product-level receipt charts stay
 * inside the grocery section and never leak into My/Family Stats.
 */
readonly class GroceryReceiptStats
{
    public function __construct(
        private CarbonImmutable $from,
        private CarbonImmutable $to,
        private ?int $groceryAccountId = null,
        private ?ReceiptProvider $provider = null,
    ) {}

    /**
     * @return Builder<GroceryReceipt>
     */
    private function receipts(): Builder
    {
        return $this->receiptsAnytime()
            ->whereBetween('purchased_at', [$this->from->startOfDay(), $this->to->endOfDay()]);
    }

    /**
     * Account and provider filters without the date range, so a product's
     * first-ever purchase can sit outside the selected dates.
     *
     * @return Builder<GroceryReceipt>
     */
    private function receiptsAnytime(): Builder
    {
        return GroceryReceipt::query()
            ->when($this->groceryAccountId !== null, fn (Builder $q) => $q->where('grocery_account_id', $this->groceryAccountId))
            ->when($this->provider !== null, fn (Builder $q) => $q->where('provider', $this->provider));
    }

    /**
     * @return Builder<GroceryReceiptItem>
     */
    private function items(): Builder
    {
        return GroceryReceiptItem::query()
            ->whereIn('grocery_receipt_id', $this->receipts()->select('id'));
    }

    /**
     * @return array<int, array{category_name: string, spend_cents: int, item_count: int, share: int}>
     */
    public function productCategorySpend(): array
    {
        $rows = $this->items()
            ->leftJoin('product_categories', 'product_categories.id', '=', 'grocery_receipt_items.product_category_id')
            ->groupBy('grocery_receipt_items.product_category_id', 'product_categories.name')
            ->selectRaw("COALESCE(product_categories.name, 'Uncategorized') as category_name")
            ->selectRaw('SUM(grocery_receipt_items.total_cents) as spend_cents, COUNT(*) as item_count')
            ->orderByDesc('spend_cents')
            ->get()
            ->map(fn ($row) => [
                'category_name' => $row->category_name,
                'spend_cents' => (int) $row->spend_cents,
                'item_count' => (int) $row->item_count,
            ])
            ->all();

        $total = array_sum(array_column($rows, 'spend_cents'));

        return array_map(function (array $row) use ($total) {
            $row['share'] = $this->shareOf($row['spend_cents'], $total);

            return $row;
        }, $rows);
    }

    /**
     * Share is of every item in the range, not of the limited list.
     *
     * @return array<int, array{name: string, spend_cents: int, times_bought: int, share: int}>
     */
    public function topProducts(int $limit = 15): array
    {
        $total = (int) $this->items()->sum('total_cents');

        return $this->items()
            ->groupBy('name')
            ->selectRaw('name, SUM(total_cents) as spend_cents, COUNT(*) as times_bought')
            ->orderByDesc('spend_cents')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'spend_cents' => (int) $row->spend_cents,
                'times_bought' => (int) $row->times_bought,
                'share' => $this->shareOf((int) $row->spend_cents, $total),
            ])
            ->all();
    }

    /**
     * @return array<int, array{period: string, receipts: int, spend_cents: int, avg_basket_cents: int}>
     */
    public function basketSizeOverTime(): array
    {
        return $this->receipts()
            ->groupByRaw("date_trunc('month', purchased_at)")
            ->selectRaw("date_trunc('month', purchased_at) as period")
            ->selectRaw('COUNT(*) as receipts, SUM(total_cents) as spend_cents, AVG(total_cents) as avg_basket_cents')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => CarbonImmutable::parse($row->period)->format('M Y'),
                'receipts' => (int) $row->receipts,
                'spend_cents' => (int) $row->spend_cents,
                'avg_basket_cents' => (int) round($row->avg_basket_cents),
            ])
            ->all();
    }

    /**
     * The five figures behind GroceryStatsOverview in two grouped queries
     * (receipts, then items for VAT) instead of ~8 separate ones.
     *
     * @return array{
     *     receipt_count: int,
     *     total_spent_cents: int,
     *     average_basket_cents: int,
     *     total_vat_cents: int,
     *     linked_percentage: int,
     * }
     */
    public function overview(): array
    {
        $totals = $this->receipts()
            ->selectRaw('COUNT(*) as receipt_count')
            ->selectRaw('COALESCE(SUM(total_cents), 0) as total_spent_cents')
            ->selectRaw('COUNT(transaction_id) as linked_count')
            ->first();

        $count = (int) $totals->receipt_count;
        $spent = (int) $totals->total_spent_cents;

        return [
            'receipt_count' => $count,
            'total_spent_cents' => $spent,
            'average_basket_cents' => $count === 0 ? 0 : (int) round($spent / $count),
            'total_vat_cents' => $this->totalVatCents(),
            'linked_percentage' => $count === 0 ? 0 : (int) round((int) $totals->linked_count / $count * 100),
        ];
    }

    public function receiptCount(): int
    {
        return $this->receipts()->count();
    }

    public function totalSpentCents(): int
    {
        return (int) $this->receipts()->sum('total_cents');
    }

    public function averageBasketCents(): int
    {
        $count = $this->receiptCount();

        return $count === 0 ? 0 : (int) round($this->totalSpentCents() / $count);
    }

    /**
     * Approximate VAT paid: each item's line total is VAT-inclusive at its
     * class rate, so the tax portion is total - total / (1 + rate/100),
     * rounded per line and summed. Done in SQL rather than loading every
     * item row into PHP.
     */
    public function totalVatCents(): int
    {
        return (int) $this->items()
            ->whereNotNull('vat_rate')
            ->selectRaw('COALESCE(SUM(ROUND(total_cents - total_cents / (1 + vat_rate / 100.0))), 0) as vat_cents')
            ->value('vat_cents');
    }

    /**
     * Share of receipts in range linked to a bank transaction, 0-100.
     */
    public function linkedPercentage(): int
    {
        $total = $this->receiptCount();

        if ($total === 0) {
            return 0;
        }

        $linked = $this->receipts()->whereNotNull('transaction_id')->count();

        return (int) round($linked / $total * 100);
    }

    /**
     * Totals for this range, the equal-length window before it, and the
     * same dates one year earlier. Account and provider filters carry over.
     *
     * @return array{
     *     current: array{receipt_count: int, total_spent_cents: int, average_basket_cents: int},
     *     previous: array{receipt_count: int, total_spent_cents: int, average_basket_cents: int},
     *     last_year: array{receipt_count: int, total_spent_cents: int, average_basket_cents: int},
     * }
     */
    public function periodComparison(): array
    {
        [$previousFrom, $previousTo] = PeriodChange::previousWindow($this->from, $this->to);

        return [
            'current' => $this->basketTotals(),
            'previous' => $this->forRange($previousFrom, $previousTo)->basketTotals(),
            'last_year' => $this->forRange($this->from->subYear(), $this->to->subYear())->basketTotals(),
        ];
    }

    public function comparisonHint(): string
    {
        return PeriodChange::hint($this->from, $this->to);
    }

    /**
     * Monday-first trip spend. Every weekday is present, with zeros where
     * nothing was bought, and spend split by receipt currency.
     *
     * @return array<int, array{weekday: string, receipts: int, spend_cents: array<string, int>}>
     */
    public function spendByWeekday(): array
    {
        $rows = $this->receipts()
            ->groupByRaw('EXTRACT(ISODOW FROM purchased_at)::int')
            ->groupBy('currency_code')
            ->selectRaw('EXTRACT(ISODOW FROM purchased_at)::int as weekday')
            ->selectRaw('currency_code')
            ->selectRaw('COUNT(*) as receipts')
            ->selectRaw('COALESCE(SUM(total_cents), 0) as spend_cents')
            ->get();

        return array_map(fn (array $day) => [
            'weekday' => $day['weekday'],
            'receipts' => $day['count'],
            'spend_cents' => $day['spend_cents'],
        ], Weekdays::fill($rows, 'receipts'));
    }

    /**
     * One row per store name and format. Format stays in the group because
     * the same banner can be a hypermarket and a convenience shop.
     *
     * @return Builder<GroceryReceipt>
     */
    public function spendByStoreQuery(): Builder
    {
        return $this->receipts()
            ->groupByRaw("COALESCE(store_name, 'Unknown store'), store_format")
            ->selectRaw('MIN(id) as id')
            ->selectRaw("COALESCE(store_name, 'Unknown store') as store_name")
            ->selectRaw('store_format')
            ->selectRaw('COUNT(*) as receipts')
            ->selectRaw('COALESCE(SUM(total_cents), 0) as spend_cents')
            ->selectRaw('COALESCE(ROUND(AVG(total_cents)), 0) as avg_basket_cents')
            ->selectRaw('COALESCE(ROUND(100.0 * SUM(total_cents) / NULLIF(SUM(SUM(total_cents)) OVER (), 0)), 0) as share');
    }

    /**
     * @return array<int, array{store_name: string, store_format: string|null, receipts: int, spend_cents: int, avg_basket_cents: int, share: int}>
     */
    public function spendByStore(): array
    {
        return $this->spendByStoreQuery()
            ->orderByDesc('spend_cents')
            ->get()
            ->map(fn ($row) => [
                'store_name' => $row->store_name,
                'store_format' => $row->store_format,
                'receipts' => (int) $row->receipts,
                'spend_cents' => (int) $row->spend_cents,
                'avg_basket_cents' => (int) $row->avg_basket_cents,
                'share' => (int) $row->share,
            ])
            ->all();
    }

    /**
     * Products bought in more than half the months the range covers.
     * A range shorter than two months cannot separate a staple from a
     * one-off, so the query matches nothing.
     *
     * @return Builder<GroceryReceiptItem>
     */
    public function staplesQuery(): Builder
    {
        $query = $this->items()
            ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
            ->groupBy('grocery_receipt_items.name')
            ->selectRaw('MIN(grocery_receipt_items.id) as id')
            ->selectRaw('grocery_receipt_items.name as name')
            ->selectRaw('COALESCE(SUM(grocery_receipt_items.total_cents), 0) as spend_cents')
            ->selectRaw('COALESCE(SUM(grocery_receipt_items.quantity), 0) as quantity')
            ->selectRaw("COUNT(DISTINCT date_trunc('month', grocery_receipts.purchased_at)) as months");

        $months = $this->monthsSpanned();

        if ($months < 2) {
            return $query->whereRaw('1 = 0');
        }

        return $query->havingRaw(
            "COUNT(DISTINCT date_trunc('month', grocery_receipts.purchased_at)) >= ?",
            [intdiv($months, 2) + 1],
        );
    }

    /**
     * @return array<int, array{name: string, months: int, quantity: float, spend_cents: int}>
     */
    public function staples(): array
    {
        return $this->staplesQuery()
            ->orderByDesc('months')
            ->orderByDesc('spend_cents')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'months' => (int) $row->months,
                'quantity' => (float) $row->quantity,
                'spend_cents' => (int) $row->spend_cents,
            ])
            ->all();
    }

    /**
     * Linked receipt totals divided by household bank expenses on the same
     * dates. Grocery account and provider filters apply to the receipts
     * only — the denominator stays every expense in the range, per currency,
     * so a Maxi-only filter does not shrink the card-spend baseline.
     * Currencies with no bank expenses are omitted.
     *
     * @return array<string, array{linked_cents: int, expense_cents: int, share: int}>
     */
    public function cardSpendShare(): array
    {
        $linked = $this->receipts()
            ->whereNotNull('transaction_id')
            ->groupBy('currency_code')
            ->selectRaw('currency_code, COALESCE(SUM(total_cents), 0) as linked_cents')
            ->pluck('linked_cents', 'currency_code');

        $expenses = (new TransactionStats(
            userId: null,
            from: $this->from,
            to: $this->to,
        ))->totalExpenseByCurrency();

        $shares = [];

        foreach (collect($linked->keys())->merge(array_keys($expenses))->unique()->sort() as $currency) {
            $expenseCents = $expenses[$currency] ?? 0;

            if ($expenseCents === 0) {
                continue;
            }

            $linkedCents = (int) ($linked[$currency] ?? 0);

            $shares[$currency] = [
                'linked_cents' => $linkedCents,
                'expense_cents' => $expenseCents,
                'share' => (int) round($linkedCents / $expenseCents * 100),
            ];
        }

        return $shares;
    }

    /**
     * @return array{receipt_count: int, total_spent_cents: int, average_basket_cents: int}
     */
    private function basketTotals(): array
    {
        $totals = $this->receipts()
            ->selectRaw('COUNT(*) as receipt_count')
            ->selectRaw('COALESCE(SUM(total_cents), 0) as total_spent_cents')
            ->first();

        $count = (int) $totals->receipt_count;
        $spent = (int) $totals->total_spent_cents;

        return [
            'receipt_count' => $count,
            'total_spent_cents' => $spent,
            'average_basket_cents' => $count === 0 ? 0 : (int) round($spent / $count),
        ];
    }

    private function forRange(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from, $to, $this->groceryAccountId, $this->provider);
    }

    /**
     * Average number of lines on a receipt in this range.
     */
    public function linesPerBasket(): float
    {
        $receipts = $this->receiptCount();

        if ($receipts === 0) {
            return 0.0;
        }

        return round($this->items()->count() / $receipts, 1);
    }

    /**
     * @return array{uncategorized_cents: int, spend_cents: int, share: int}
     */
    public function uncategorizedItemShare(): array
    {
        $row = $this->items()
            ->selectRaw('COALESCE(SUM(total_cents), 0) as spend_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN product_category_id IS NULL THEN total_cents ELSE 0 END), 0) as uncategorized_cents')
            ->first();

        $spend = (int) $row->spend_cents;
        $uncategorized = (int) $row->uncategorized_cents;

        return [
            'uncategorized_cents' => $uncategorized,
            'spend_cents' => $spend,
            'share' => $this->shareOf($uncategorized, $spend),
        ];
    }

    /**
     * Receipt totals with no matched bank payment, per currency.
     *
     * @return array<string, int>
     */
    public function unlinkedTotalsByCurrency(): array
    {
        return $this->receipts()
            ->whereNull('transaction_id')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->selectRaw('currency_code, COALESCE(SUM(total_cents), 0) as total_cents')
            ->pluck('total_cents', 'currency_code')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }

    /**
     * Unlinked receipt totals beside household ATM withdrawals on the same
     * dates. Grocery filters apply to the receipts only.
     *
     * @return array<string, array{unlinked_cents: int, atm_cents: int}>
     */
    public function cashGap(): array
    {
        $unlinked = $this->unlinkedTotalsByCurrency();
        $atm = (new TransactionStats(
            userId: null,
            from: $this->from,
            to: $this->to,
        ))->atmWithdrawalTotalsByCurrency();

        $gap = [];

        foreach (collect(array_keys($unlinked))->merge(array_keys($atm))->unique()->sort() as $currency) {
            $unlinkedCents = $unlinked[$currency] ?? 0;
            $atmCents = $atm[$currency] ?? 0;

            if ($unlinkedCents === 0 && $atmCents === 0) {
                continue;
            }

            $gap[$currency] = [
                'unlinked_cents' => $unlinkedCents,
                'atm_cents' => $atmCents,
            ];
        }

        return $gap;
    }

    /**
     * @return array<int, array{rate: string, spend_cents: int, vat_cents: int}>
     */
    public function vatByRate(): array
    {
        return $this->items()
            ->groupBy('vat_rate')
            ->selectRaw('vat_rate')
            ->selectRaw('COALESCE(SUM(total_cents), 0) as spend_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN vat_rate IS NULL THEN 0 ELSE ROUND(total_cents - total_cents / (1 + vat_rate / 100.0)) END), 0) as vat_cents')
            ->orderByRaw('vat_rate NULLS LAST')
            ->get()
            ->map(fn ($row) => [
                'rate' => $row->vat_rate === null
                    ? 'No rate'
                    : rtrim(rtrim(number_format((float) $row->vat_rate, 2), '0'), '.').'%',
                'spend_cents' => (int) $row->spend_cents,
                'vat_cents' => (int) $row->vat_cents,
            ])
            ->all();
    }

    /**
     * Names bought at least three times in this range whose latest unit
     * price sits above the average of those purchases.
     *
     * @return Builder<GroceryReceiptItem>
     */
    public function pricesMovedQuery(int $minTimes = 3): Builder
    {
        $lastPrice = '(ARRAY_AGG(grocery_receipt_items.unit_price_cents ORDER BY grocery_receipts.purchased_at DESC, grocery_receipt_items.id DESC))[1]';

        return $this->items()
            ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
            ->groupBy('grocery_receipt_items.name')
            ->selectRaw('MIN(grocery_receipt_items.id) as id')
            ->selectRaw('grocery_receipt_items.name as name')
            ->selectRaw('COUNT(*) as times_bought')
            ->selectRaw('ROUND(AVG(grocery_receipt_items.unit_price_cents)) as avg_unit_price_cents')
            ->selectRaw('MIN(grocery_receipt_items.unit_price_cents) as min_unit_price_cents')
            ->selectRaw("{$lastPrice} as last_unit_price_cents")
            ->selectRaw("ROUND(100.0 * ({$lastPrice} - AVG(grocery_receipt_items.unit_price_cents)) / NULLIF(AVG(grocery_receipt_items.unit_price_cents), 0), 1) as raise_percent")
            ->havingRaw('COUNT(*) >= ?', [$minTimes])
            ->havingRaw("{$lastPrice} > AVG(grocery_receipt_items.unit_price_cents)");
    }

    /**
     * A basket more than twice the average of the other baskets at that
     * store. Fewer than three trips and the store is skipped.
     *
     * @return Builder<GroceryReceipt>
     */
    public function unusualBasketsQuery(int $minVisits = 3, float $multiple = 2): Builder
    {
        $stats = $this->receipts()
            ->groupByRaw("COALESCE(store_name, 'Unknown store'), currency_code")
            ->selectRaw("COALESCE(store_name, 'Unknown store') as store_name")
            ->selectRaw('currency_code')
            ->selectRaw('COUNT(*) as visits')
            ->selectRaw('SUM(total_cents) as spend_cents');

        $others = '((store_stats.spend_cents - grocery_receipts.total_cents) / NULLIF(store_stats.visits - 1, 0))';

        return $this->receipts()
            ->select('grocery_receipts.*')
            ->selectRaw("ROUND({$others}) as typical_cents")
            ->joinSub($stats, 'store_stats', function ($join): void {
                $join->whereRaw("store_stats.store_name = COALESCE(grocery_receipts.store_name, 'Unknown store')")
                    ->whereColumn('store_stats.currency_code', 'grocery_receipts.currency_code');
            })
            ->where('store_stats.visits', '>=', $minVisits)
            ->whereRaw("grocery_receipts.total_cents > ? * {$others}", [$multiple]);
    }

    /**
     * Products whose first purchase, including dates outside this range,
     * still falls inside it.
     *
     * @return Builder<GroceryReceiptItem>
     */
    public function firstTimeProductsQuery(): Builder
    {
        $from = $this->from->startOfDay();
        $to = $this->to->endOfDay();

        return GroceryReceiptItem::query()
            ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
            ->whereIn('grocery_receipts.id', $this->receiptsAnytime()->select('id'))
            ->groupBy('grocery_receipt_items.name')
            ->selectRaw('MIN(grocery_receipt_items.id) as id')
            ->selectRaw('grocery_receipt_items.name as name')
            ->selectRaw('MIN(grocery_receipts.purchased_at) as first_purchased_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN grocery_receipts.purchased_at BETWEEN ? AND ? THEN grocery_receipt_items.total_cents ELSE 0 END), 0) as spend_cents', [$from, $to])
            ->selectRaw('COALESCE(SUM(CASE WHEN grocery_receipts.purchased_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as times_bought', [$from, $to])
            ->havingRaw('MIN(grocery_receipts.purchased_at) BETWEEN ? AND ?', [$from, $to]);
    }

    private function shareOf(int $part, int $total): int
    {
        return $total === 0 ? 0 : (int) round($part / $total * 100);
    }

    private function monthsSpanned(): int
    {
        $start = $this->from->startOfMonth();
        $end = $this->to->startOfMonth();

        return ($end->year - $start->year) * 12 + ($end->month - $start->month) + 1;
    }
}
