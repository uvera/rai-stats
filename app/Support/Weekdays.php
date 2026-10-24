<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Monday-first weekday buckets. Postgres ISODOW is 1 (Monday) through 7
 * (Sunday); days with no rows stay in the result as zeros so a chart axis
 * does not shift between weeks.
 */
final class Weekdays
{
    /**
     * @var array<int, string>
     */
    public const LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * @param  iterable<int, object>  $rows  Each row has weekday (1-7), currency_code, spend_cents, and $countKey.
     * @return array<int, array{weekday: string, count: int, spend_cents: array<string, int>}>
     */
    public static function fill(iterable $rows, string $countKey): array
    {
        /** @var Collection<int, object> $rows */
        $rows = collect($rows);
        $currencies = $rows->pluck('currency_code')->unique()->sort()->values();

        $byDay = [];

        foreach ($rows as $row) {
            $index = ((int) $row->weekday) - 1;

            if ($index < 0 || $index > 6) {
                continue;
            }

            $byDay[$index]['count'] = ($byDay[$index]['count'] ?? 0) + (int) $row->{$countKey};
            $byDay[$index]['spend'][$row->currency_code] = (int) $row->spend_cents;
        }

        $days = [];

        foreach (self::LABELS as $index => $label) {
            $spend = [];

            foreach ($currencies as $currency) {
                $spend[$currency] = $byDay[$index]['spend'][$currency] ?? 0;
            }

            $days[] = [
                'weekday' => $label,
                'count' => $byDay[$index]['count'] ?? 0,
                'spend_cents' => $spend,
            ];
        }

        return $days;
    }
}
