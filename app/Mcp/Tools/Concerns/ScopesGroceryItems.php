<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\GroceryReceiptItem;
use App\Support\McpTokenScope;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;

/**
 * Applies the authenticating token's scope to a grocery receipt item query.
 * Grocery data is owned via GroceryReceipt -> GroceryAccount.user_id: a
 * self-scoped token only sees items from its own accounts, a family-scoped
 * token sees every account's items (including accounts with no user set).
 */
trait ScopesGroceryItems
{
    /**
     * @return Builder<GroceryReceiptItem>
     */
    protected function scopedItems(Request $request): Builder
    {
        $userId = McpTokenScope::resolveUserId($request);

        return GroceryReceiptItem::query()
            ->when($userId !== null, fn (Builder $query) => $query->whereHas(
                'receipt.account',
                fn (Builder $account) => $account->where('user_id', $userId),
            ));
    }
}
