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
        return GroceryReceipt::query()
            ->whereBetween('purchased_at', [$this->from->startOfDay(), $this->to->endOfDay()])
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
     * @return array<int, array{category_name: string, spend_cents: int, item_count: int}>
     */
    public function productCategorySpend(): array
    {
        return $this->items()
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
    }

    /**
     * @return array<int, array{name: string, spend_cents: int, times_bought: int}>
     */
    public function topProducts(int $limit = 15): array
    {
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
            ->selectRaw('COALESCE(ROUND(AVG(total_cents)), 0) as avg_basket_cents');
    }

    /**
     * @return array<int, array{store_name: string, store_format: string|null, receipts: int, spend_cents: int, avg_basket_cents: int}>
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

    private function monthsSpanned(): int
    {
        $start = $this->from->startOfMonth();
        $end = $this->to->startOfMonth();

        return ($end->year - $start->year) * 12 + ($end->month - $start->month) + 1;
    }
}
