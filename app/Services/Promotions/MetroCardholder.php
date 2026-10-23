<?php

namespace App\Services\Promotions;

/**
 * Metro card ids look like "RS_22_760829_1": country, then store.
 */
class MetroCardholder
{
    /**
     * @return array{country: string, store: string}|null
     */
    public static function parse(string $cardholderId): ?array
    {
        $parts = explode('_', $cardholderId);

        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [
            'country' => strtolower($parts[0]),
            'store' => $parts[1],
        ];
    }
}
