<?php

namespace App\Support;

/**
 * Turns a JSON number (often a float of a currency amount) into integer
 * cents. This is not Money::toCents(), which only accepts a decimal string.
 */
final class Cents
{
    public static function fromJson(mixed $amount): ?int
    {
        if (! is_numeric($amount)) {
            return null;
        }

        return (int) round(((float) $amount) * 100);
    }
}
