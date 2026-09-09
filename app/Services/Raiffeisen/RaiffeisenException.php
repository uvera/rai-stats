<?php

namespace App\Services\Raiffeisen;

use RuntimeException;

class RaiffeisenException extends RuntimeException
{
    /**
     * The bank's row/field layout is entirely reverse-engineered, so a
     * backend change shows up as a row that no longer matches what a DTO
     * factory expects. Turn that into a domain error carrying the offending
     * shape rather than an unhandled TypeError deep in a constructor.
     *
     * @param  array<int, mixed>  $row
     */
    public static function malformedRow(string $source, array $row, string $detail): self
    {
        return new self(sprintf(
            'Unexpected %s row from the bank (%s): %s',
            $source,
            $detail,
            json_encode(array_slice($row, 0, 20)),
        ));
    }
}
