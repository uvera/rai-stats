<?php

namespace App\Support;

/**
 * Pure interval-arithmetic used by the import wizard: merging overlapping or
 * adjacent date ranges into a minimal covered set, and subtracting an
 * already-covered set from a requested range to find what's actually left
 * to import.
 */
class DateRangeMerger
{
    /**
     * @param  DateRange[]  $ranges
     * @return DateRange[] Sorted, non-overlapping, non-adjacent ranges.
     */
    public static function merge(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }

        $sorted = $ranges;
        usort($sorted, fn (DateRange $a, DateRange $b) => $a->from <=> $b->from);

        $merged = [array_shift($sorted)];

        foreach ($sorted as $range) {
            $last = end($merged);
            $lastKey = array_key_last($merged);

            if ($last->touches($range)) {
                $merged[$lastKey] = $last->merge($range);
            } else {
                $merged[] = $range;
            }
        }

        return array_values($merged);
    }

    /**
     * The portion(s) of $requested not already covered by $covered.
     *
     * @param  DateRange[]  $covered
     * @return DateRange[] Zero or more gap ranges, in chronological order.
     */
    public static function subtract(DateRange $requested, array $covered): array
    {
        $mergedCovered = self::merge($covered);

        $overlapping = array_values(array_filter(
            $mergedCovered,
            fn (DateRange $c) => $c->overlaps($requested)
        ));

        usort($overlapping, fn (DateRange $a, DateRange $b) => $a->from <=> $b->from);

        $gaps = [];
        $cursor = $requested->from;

        foreach ($overlapping as $coveredRange) {
            if ($coveredRange->from > $cursor) {
                $gaps[] = new DateRange($cursor, $coveredRange->from->modify('-1 day'));
            }

            $cursor = max($cursor, $coveredRange->to->modify('+1 day'));
        }

        if ($cursor <= $requested->to) {
            $gaps[] = new DateRange($cursor, $requested->to);
        }

        return $gaps;
    }
}
