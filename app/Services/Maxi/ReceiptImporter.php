<?php

namespace App\Services\Maxi;

use App\Enums\CategorySource;
use App\Models\MaxiAccount;
use App\Models\MaxiReceipt;
use App\Services\Maxi\Data\InvoiceSummary;
use App\Services\Maxi\Data\ParsedItem;
use App\Support\ProductCategorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns one downloaded eReceipt into a MaxiReceipt (+ items), idempotently.
 *
 * De-duplication: a receipt whose (account, invoice_hash) already exists is
 * left untouched and returned as-is - callers should only download a PDF for
 * hashes not already stored, but this is the backstop. $force re-parses an
 * existing receipt, replacing its items rather than doubling them.
 */
class ReceiptImporter
{
    public function __construct(
        private readonly ReceiptPdfParser $parser = new ReceiptPdfParser,
        private readonly ReceiptTransactionMatcher $matcher = new ReceiptTransactionMatcher,
    ) {}

    public function import(MaxiAccount $account, InvoiceSummary $summary, string $pdfBytes, bool $force = false): MaxiReceipt
    {
        $existing = MaxiReceipt::query()
            ->where('maxi_account_id', $account->id)
            ->where('invoice_hash', $summary->invoiceHash)
            ->first();

        if ($existing !== null && ! $force) {
            return $existing;
        }

        $parsed = $this->parser->parse($pdfBytes);

        if (! $parsed->itemsReconcile()) {
            Log::warning('maxi.receipt.reconcile_failed', [
                'account_id' => $account->id,
                'invoice_hash' => $summary->invoiceHash,
                'items_total' => $parsed->itemsTotalCents(),
                'parsed_total' => $parsed->totalCents,
            ]);
        }

        return DB::transaction(function () use ($account, $summary, $parsed, $existing) {
            $receipt = $existing ?? new MaxiReceipt;

            $receipt->fill([
                'maxi_account_id' => $account->id,
                'invoice_hash' => $summary->invoiceHash,
                'pfr_number' => $parsed->pfrNumber,
                'purs_vl' => $parsed->pursVl,
                'store_name' => $summary->storeName,
                'store_address' => $summary->storeAddress,
                'store_format' => $summary->storeFormat,
                'purchased_at' => $summary->purchasedAt,
                'total_cents' => $parsed->totalCents ?? $summary->totalCents,
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
                    'total_cents' => $item->totalCents,
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
