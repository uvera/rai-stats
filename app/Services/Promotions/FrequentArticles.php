<?php

namespace App\Services\Promotions;

use App\Models\GroceryAccount;
use App\Models\GroceryReceiptItem;
use Illuminate\Support\Collection;

/**
 * Receipt-line names bought at least twice on one account in the last 180 days.
 * The same spelling is one article, matching article prices.
 */
class FrequentArticles
{
    /**
     * @return Collection<int, GroceryReceiptItem>
     */
    public static function forAccount(GroceryAccount $account): Collection
    {
        return GroceryReceiptItem::query()
            ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
            ->where('grocery_receipts.grocery_account_id', $account->id)
            ->where('grocery_receipts.purchased_at', '>=', now()->subDays(180))
            ->groupBy('grocery_receipt_items.name')
            ->havingRaw('COUNT(*) >= 2')
            ->selectRaw('grocery_receipt_items.name as name')
            ->selectRaw('COUNT(*) as times_bought')
            ->selectRaw('MAX(grocery_receipts.purchased_at) as last_purchased_at')
            ->selectRaw('(ARRAY_AGG(grocery_receipt_items.unit_price_cents ORDER BY grocery_receipts.purchased_at DESC, grocery_receipt_items.id DESC))[1] as last_unit_price_cents')
            ->get();
    }
}
