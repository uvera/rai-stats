<?php

namespace App\Services\Raiffeisen\Data;

use App\Services\Raiffeisen\Money;
use DateTimeImmutable;

readonly class ReservedTransaction
{
    public function __construct(
        public DateTimeImmutable $date,
        public string $place,
        public int $amountCents,
        public string $currencyCode,
        public string $currencyCodeNumeric,
    ) {}

    /**
     * @param  array<int, string>  $row  A single row from
     *                                    GetTransactionalAccountReservedFunds.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            date: DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $row[1]),
            place: $row[2],
            amountCents: -Money::toCents($row[3]),
            currencyCode: $row[4],
            currencyCodeNumeric: $row[5],
        );
    }
}
