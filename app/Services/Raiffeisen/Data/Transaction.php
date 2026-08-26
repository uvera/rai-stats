<?php

namespace App\Services\Raiffeisen\Data;

use App\Services\Raiffeisen\Money;
use DateTimeImmutable;

readonly class Transaction
{
    public function __construct(
        public string $currencyCodeNumeric,
        public string $currencyCode,
        public DateTimeImmutable $date,
        public string $place,
        public string $reference,
        public int $amountCents,
        public string $description,
        public string $bankTransactionId,
        public TransactionType $type,
    ) {}

    /**
     * @param  array<int, mixed>  $row  A single transaction row from
     *                                   GetTransactionalAccountTurnover, indexed
     *                                   exactly as the bank returns it.
     */
    public static function fromRow(array $row): self
    {
        $creditAmount = (string) $row[8];
        $debitAmount = (string) $row[9];

        // Credit (money in) comes back as a positive raw value that must be
        // negated for our sign convention... except the bank's own semantics
        // here are inverted from plain-English "credit/debit": a nonzero
        // value in the credit slot is actually an outflow. Verified against
        // real data (ATM withdrawals, POS purchases) during manual analysis.
        $amountCents = 0;
        if (bccomp($creditAmount, '0', 2) !== 0) {
            $amountCents = -Money::toCents($creditAmount);
        }
        if (bccomp($debitAmount, '0', 2) !== 0) {
            $amountCents = Money::toCents($debitAmount);
        }

        return new self(
            currencyCodeNumeric: $row[1],
            currencyCode: $row[2],
            date: DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $row[3]),
            place: $row[6],
            reference: $row[7],
            amountCents: $amountCents,
            description: $row[11],
            bankTransactionId: $row[12],
            type: TransactionType::fromRaw($row[13]),
        );
    }
}
