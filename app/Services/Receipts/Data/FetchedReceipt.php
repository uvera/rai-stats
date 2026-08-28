<?php

namespace App\Services\Receipts\Data;

/**
 * One receipt pulled from a provider, ready for ReceiptImporter: the parsed
 * line items + totals, and the original PDF bytes when the provider exposes
 * one (kept for the human-readable raw text).
 */
readonly class FetchedReceipt
{
    public function __construct(
        public ParsedReceipt $parsed,
        public ?string $pdfBytes = null,
    ) {}
}
