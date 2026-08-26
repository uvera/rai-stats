<?php

namespace App\Services\Raiffeisen\Data;

use App\Services\Raiffeisen\Money;
use DateTimeImmutable;

readonly class AccountBalance
{
    public function __construct(
        public string $number,
        public string $description,
        public string $currencyCode,
        public string $currencyCodeNumeric,
        public string $productCoreId,
        public int $totalAmountCents,
        public int $availableAmountCents,
        public ?int $lastTransactionAmountCents,
        public ?DateTimeImmutable $lastTransactionDate,
    ) {}

    /**
     * @param  array<int, string>  $row  A single row from GetAllAccountBalance,
     *                                    indexed exactly as the bank returns it.
     */
    public static function fromRow(array $row): self
    {
        $lastAmount = $row[6] === '' ? null : Money::toCents($row[6]);
        $lastDate = $row[7] === '' ? null : DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $row[7]);

        return new self(
            number: $row[1],
            description: $row[2],
            currencyCode: $row[3],
            currencyCodeNumeric: $row[14],
            productCoreId: $row[13],
            totalAmountCents: Money::toCents($row[4]),
            availableAmountCents: Money::toCents($row[5]),
            lastTransactionAmountCents: $lastAmount,
            lastTransactionDate: $lastDate ?: null,
        );
    }
}
