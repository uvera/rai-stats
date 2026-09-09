<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Page-filter state (the date pickers, the period/account selects) is
 * Livewire state pushed from the browser, so a malformed "from"/"to" would
 * otherwise blow up CarbonImmutable::parse() with an InvalidFormatException
 * on the next widget render. Fall back to the default window instead.
 */
class DateFilter
{
    public static function parseOr(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (blank($value)) {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
