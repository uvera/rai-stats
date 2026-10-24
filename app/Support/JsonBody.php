<?php

namespace App\Support;

/**
 * Shared BOM-stripping JSON decode for the bank and loyalty HTTP clients.
 * Callers throw their own exception when this returns null, so a bad
 * payload still surfaces as that client's error type.
 */
final class JsonBody
{
    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $body): ?array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
