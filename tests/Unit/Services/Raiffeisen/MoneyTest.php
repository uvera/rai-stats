<?php

namespace Tests\Unit\Services\Raiffeisen;

use App\Services\Raiffeisen\Money;
use App\Services\Raiffeisen\RaiffeisenException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_converts_plain_decimal_strings_to_cents(): void
    {
        $this->assertSame(2000000, Money::toCents('20000'));
        $this->assertSame(6366436, Money::toCents('63664.36'));
        $this->assertSame(-50000, Money::toCents('-500.00'));
        $this->assertSame(0, Money::toCents('0'));
    }

    public function test_rounds_half_up_rather_than_truncating(): void
    {
        $this->assertSame(1235, Money::toCents('12.345'));
        $this->assertSame(-1235, Money::toCents('-12.345'));
        $this->assertSame(1234, Money::toCents('12.344'));
    }

    public function test_rejects_anything_that_is_not_a_bare_decimal(): void
    {
        foreach (['1,234.56', '12,50', '€20', '20 000', '', 'n/a'] as $bad) {
            try {
                Money::toCents($bad);
                $this->fail("Expected a RaiffeisenException for \"{$bad}\"");
            } catch (RaiffeisenException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
