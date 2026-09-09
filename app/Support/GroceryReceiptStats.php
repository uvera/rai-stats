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
}
