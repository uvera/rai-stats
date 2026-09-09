<?php

namespace Tests\Unit\Support;

use App\Support\DateFilter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateFilterTest extends TestCase
{
    public function test_parses_a_valid_value(): void
    {
        $parsed = DateFilter::parseOr('2026-03-15', CarbonImmutable::parse('2000-01-01'));

        $this->assertSame('2026-03-15', $parsed->toDateString());
    }

    public function test_falls_back_on_blank_or_malformed_input(): void
    {
        $fallback = CarbonImmutable::parse('2026-01-01');

        $this->assertTrue(DateFilter::parseOr(null, $fallback)->equalTo($fallback));
        $this->assertTrue(DateFilter::parseOr('', $fallback)->equalTo($fallback));
        $this->assertTrue(DateFilter::parseOr('not-a-date', $fallback)->equalTo($fallback));
        $this->assertTrue(DateFilter::parseOr('2026-13-45', $fallback)->equalTo($fallback));
    }
}
