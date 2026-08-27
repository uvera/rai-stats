<?php

namespace App\Services\Maxi\Data;

use Carbon\CarbonImmutable;

/**
 * One row of GET /prod/E/api/Invoice/GetInvoicesByUser.
 */
readonly class InvoiceSummary
{
    public function __construct(
        public string $invoiceHash,
        public CarbonImmutable $purchasedAt,
        public int $totalCents,
        public string $storeName,
        public ?string $storeAddress,
        public ?string $storeFormat,
        public string $pdfUrl,
    ) {}

    public static function fromRow(array $row): self
    {
        // FormattedInvoiceDate is yyyyMMdd, FormattedInvoiceTime is HHmm
        // (sometimes without a leading zero, e.g. "758" for 07:58).
        $date = (string) ($row['FormattedInvoiceDate'] ?? '');
        $time = str_pad((string) ($row['FormattedInvoiceTime'] ?? '0'), 4, '0', STR_PAD_LEFT);

        $purchasedAt = CarbonImmutable::createFromFormat('YmdHi', $date.$time)
            ?: CarbonImmutable::now();

        return new self(
            invoiceHash: (string) $row['InvoiceNumberHash'],
            purchasedAt: $purchasedAt,
            totalCents: (int) round(((float) ($row['TotalAmount'] ?? 0)) * 100),
            storeName: (string) ($row['StoreName'] ?? ''),
            storeAddress: isset($row['StoreAddress']) ? (string) $row['StoreAddress'] : null,
            // Their API key is misspelled "StoreFromat" - tolerate both.
            storeFormat: isset($row['StoreFromat'])
                ? (string) $row['StoreFromat']
                : (isset($row['StoreFormat']) ? (string) $row['StoreFormat'] : null),
            pdfUrl: (string) ($row['InvoicePdfUrl'] ?? ''),
        );
    }
}
