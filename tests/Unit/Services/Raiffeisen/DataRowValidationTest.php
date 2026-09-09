<?php

namespace Tests\Unit\Services\Raiffeisen;

use App\Services\Raiffeisen\Data\AccountBalance;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction;
use App\Services\Raiffeisen\RaiffeisenException;
use PHPUnit\Framework\TestCase;

class DataRowValidationTest extends TestCase
{
    public function test_transaction_rejects_a_short_row(): void
    {
        $this->expectException(RaiffeisenException::class);

        Transaction::fromRow(array_fill(0, 8, ''));
    }

    public function test_transaction_rejects_an_unparseable_date(): void
    {
        $row = array_fill(0, 15, '');
        $row[3] = '2026-08-24'; // wrong format - the bank sends d.m.Y H:i:s

        $this->expectException(RaiffeisenException::class);
        $this->expectExceptionMessageMatches('/date/');

        Transaction::fromRow($row);
    }

    public function test_account_balance_rejects_a_short_row(): void
    {
        $this->expectException(RaiffeisenException::class);

        AccountBalance::fromRow(array_fill(0, 10, ''));
    }

    public function test_reserved_transaction_rejects_a_short_row(): void
    {
        $this->expectException(RaiffeisenException::class);

        ReservedTransaction::fromRow(['', '24.08.2026 12:00:00', 'Place']);
    }

    public function test_a_well_formed_row_still_parses(): void
    {
        $row = array_fill(0, 15, '');
        $row[1] = '941';
        $row[2] = 'RSD';
        $row[3] = '24.08.2026 00:00:00';
        $row[9] = '100.00';
        $row[13] = 'Income';

        $this->assertSame(10000, Transaction::fromRow($row)->amountCents);
    }
}
