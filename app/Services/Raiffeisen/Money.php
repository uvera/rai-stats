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
        $decimal = trim($decimal);

        // The bank sends a bare decimal - no thousands separators, no comma
        // decimal mark, no currency symbol. Anything else means the field
        // moved or the format changed; fail loudly rather than let bcmul
        // throw a bare ValueError or silently coerce.
        if (! preg_match('/^-?\d+(\.\d+)?$/', $decimal)) {
            throw new RaiffeisenException("Not a plain decimal amount: \"{$decimal}\"");
        }

        // Scale to cents keeping extra precision, then round half-up to a
        // whole cent (inputs are 2dp in practice, so this is normally exact).
        $scaled = bcmul($decimal, '100', 4);
        $bump = str_starts_with($decimal, '-') ? '-0.5' : '0.5';

        return (int) bcadd($scaled, $bump, 0);
    }
}
