<?php

namespace App\Support;

use App\Models\MaxiReceipt;
use App\Models\MaxiReceiptItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query layer behind the Moj Maxi stats page - kept separate from
 * TransactionStats on purpose, so the product-level receipt charts stay
 * inside the Moj Maxi section and never leak into My/Family Stats.
 */
readonly class MaxiReceiptStats
{
    public function __construct(
        private CarbonImmutable $from,
        private CarbonImmutable $to,
        private ?int $maxiAccountId = null,
    ) {}

    /**
     * @return Builder<MaxiReceipt>
     */
    private function receipts(): Builder
    {
        return MaxiReceipt::query()
            ->whereBetween('purchased_at', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->when($this->maxiAccountId !== null, fn (Builder $q) => $q->where('maxi_account_id', $this->maxiAccountId));
    }

    /**
     * @return Builder<MaxiReceiptItem>
     */
    private function items(): Builder
    {
        return MaxiReceiptItem::query()
            ->whereIn('maxi_receipt_id', $this->receipts()->select('id'));
    }

    /**
     * @return array<int, array{category_name: string, spend_cents: int, item_count: int}>
     */
    public function productCategorySpend(): array
    {
        return $this->items()
            ->leftJoin('product_categories', 'product_categories.id', '=', 'maxi_receipt_items.product_category_id')
            ->groupBy('maxi_receipt_items.product_category_id', 'product_categories.name')
            ->selectRaw("COALESCE(product_categories.name, 'Uncategorized') as category_name")
            ->selectRaw('SUM(maxi_receipt_items.total_cents) as spend_cents, COUNT(*) as item_count')
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
     * class rate, so the tax portion is total - total / (1 + rate/100).
     */
    public function totalVatCents(): int
    {
        return $this->items()
            ->whereNotNull('vat_rate')
            ->get(['total_cents', 'vat_rate'])
            ->sum(fn (MaxiReceiptItem $item) => (int) round(
                $item->total_cents - $item->total_cents / (1 + ((float) $item->vat_rate) / 100)
            ));
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
