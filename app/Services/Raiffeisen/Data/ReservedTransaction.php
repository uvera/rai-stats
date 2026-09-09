<?php

namespace App\Services\Raiffeisen\Data;

use App\Services\Raiffeisen\Money;
use App\Services\Raiffeisen\RaiffeisenException;
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
        if (count($row) < 6) {
            throw RaiffeisenException::malformedRow('reserved funds', $row, 'expected at least 6 fields');
        }

        $date = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', (string) $row[1]);

        if ($date === false) {
            throw RaiffeisenException::malformedRow('reserved funds', $row, "unparseable date \"{$row[1]}\"");
        }

        return new self(
            date: $date,
            place: $row[2],
            amountCents: -Money::toCents($row[3]),
            currencyCode: $row[4],
            currencyCodeNumeric: $row[5],
        );
    }
}
