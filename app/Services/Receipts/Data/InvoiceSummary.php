<?php

namespace App\Services\Receipts\Data;

use Carbon\CarbonImmutable;

/**
 * One row of a provider's invoice list, normalised. `externalRef` is the
 * stable per-invoice id used to de-duplicate (Moj Maxi InvoiceNumberHash,
 * Metro transactionId); `remoteId` is whatever the provider needs to fetch
 * the receipt's detail (Metro passes the transactionId back in the URL).
 */
readonly class InvoiceSummary
{
    public function __construct(
        public string $externalRef,
        public CarbonImmutable $purchasedAt,
        public int $totalCents,
        public string $storeName,
        public ?string $storeAddress = null,
        public ?string $storeFormat = null,
        public ?string $pdfUrl = null,
        public ?int $netTotalCents = null,
        public ?string $remoteId = null,
    ) {}

    /**
     * A row of Moj Maxi's GET /prod/E/api/Invoice/GetInvoicesByUser.
     */
    public static function fromRow(array $row): self
    {
        // FormattedInvoiceDate is yyyyMMdd, FormattedInvoiceTime is HHmm
        // (sometimes without a leading zero, e.g. "758" for 07:58).
        $date = (string) ($row['FormattedInvoiceDate'] ?? '');
        $time = str_pad((string) ($row['FormattedInvoiceTime'] ?? '0'), 4, '0', STR_PAD_LEFT);

        $purchasedAt = CarbonImmutable::createFromFormat('YmdHi', $date.$time)
            ?: CarbonImmutable::now();

        return new self(
            externalRef: (string) $row['InvoiceNumberHash'],
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

    /**
     * A row of Metro's GET /companion-finance/v1/invoices.
     */
    public static function fromMetroRow(array $row): self
    {
        $transactionId = (string) ($row['transactionId'] ?? '');

        return new self(
            externalRef: $transactionId,
            purchasedAt: CarbonImmutable::parse((string) ($row['invoiceDate'] ?? 'now')),
            totalCents: (int) round(((float) ($row['totalAmount'] ?? 0)) * 100),
            storeName: self::metroStoreName($transactionId),
            netTotalCents: isset($row['netAmount'])
                ? (int) round(((float) $row['netAmount']) * 100)
                : null,
            remoteId: $transactionId,
        );
    }

    /**
     * Metro's invoice list carries no store name, only a transactionId like
     * "SRB_22_11_1089199_20260823123433" - the second segment is the
     * warehouse number.
     */
    private static function metroStoreName(string $transactionId): string
    {
        $parts = explode('_', $transactionId);

        return isset($parts[1]) && $parts[1] !== ''
            ? 'Metro '.$parts[1]
            : 'Metro';
    }
}
