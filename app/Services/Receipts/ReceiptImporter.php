<?php

namespace App\Services\Receipts;

use App\Enums\CategorySource;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Services\Receipts\Data\FetchedReceipt;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ParsedItem;
use App\Support\ProductCategorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns one fetched receipt into a GroceryReceipt (+ items), idempotently,
 * whichever provider it came from.
 *
 * De-duplication: a receipt whose (account, external_ref) already exists is
 * left untouched and returned as-is - callers should only fetch invoices not
 * already stored, but this is the backstop. $force re-imports an existing
 * receipt, replacing its items rather than doubling them.
 */
class ReceiptImporter
{
    public function __construct(
        private readonly ReceiptTransactionMatcher $matcher = new ReceiptTransactionMatcher,
    ) {}

    public function import(GroceryAccount $account, InvoiceSummary $summary, FetchedReceipt $fetched, bool $force = false): GroceryReceipt
    {
        $existing = GroceryReceipt::query()
            ->where('grocery_account_id', $account->id)
            ->where('external_ref', $summary->externalRef)
            ->first();

        if ($existing !== null && ! $force) {
            return $existing;
        }

        $parsed = $fetched->parsed;

        if (! $parsed->itemsReconcile()) {
            Log::warning('grocery.receipt.reconcile_failed', [
                'account_id' => $account->id,
                'provider' => $account->provider->value,
                'external_ref' => $summary->externalRef,
                'items_total' => $parsed->itemsTotalCents(),
                'parsed_total' => $parsed->totalCents,
            ]);
        }

        return DB::transaction(function () use ($account, $summary, $parsed, $existing) {
            $receipt = $existing ?? new GroceryReceipt;

            $receipt->fill([
                'grocery_account_id' => $account->id,
                'provider' => $account->provider,
                'external_ref' => $summary->externalRef,
                'pfr_number' => $parsed->pfrNumber,
                'purs_vl' => $parsed->pursVl,
                'store_name' => $summary->storeName,
                'store_address' => $summary->storeAddress,
                'store_format' => $summary->storeFormat,
                'purchased_at' => $summary->purchasedAt,
                'total_cents' => $parsed->totalCents ?? $summary->totalCents,
                'net_total_cents' => $summary->netTotalCents,
                'currency_code' => 'RSD',
                'raw_text' => $parsed->rawText,
                'synced_at' => now(),
            ]);
            $receipt->save();

            $receipt->items()->delete();

            $categorizer = new ProductCategorizer;

            foreach ($parsed->items as $item) {
                /** @var ParsedItem $item */
                $categoryId = $categorizer->categorize($item->name);

                $receipt->items()->create([
                    'line_no' => $item->lineNo,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unitPriceCents,
                    'net_unit_price_cents' => $item->netUnitPriceCents,
                    'total_cents' => $item->totalCents,
                    'net_total_cents' => $item->netTotalCents,
                    'vat_label' => $item->vatLabel,
                    'vat_rate' => $item->vatRate,
                    'product_category_id' => $categoryId,
                    'category_source' => $categoryId !== null ? CategorySource::Rule->value : null,
                ]);
            }

            $receipt->setRelation('account', $account);
            $this->matcher->autoMatch($receipt);

            return $receipt->refresh();
        });
    }
}
