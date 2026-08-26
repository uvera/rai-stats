<?php

namespace App\Services\Raiffeisen;

/**
 * Converts RaiOnline's plain decimal-string amounts (e.g. "20000", "63664.36")
 * to integer cents, without floating point.
 */
class Money
{
    public static function toCents(string $decimal): int
    {
        return (int) bcmul($decimal, '100', 0);
    }
}
