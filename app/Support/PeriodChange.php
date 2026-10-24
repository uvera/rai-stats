<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\HtmlString;

/**
 * Equal-length comparison window and the "+12% vs previous" labels shared
 * by the bank and grocery overview cards.
 */
final class PeriodChange
{
    /**
     * The window of the same length immediately before $from.
     * A range of 31 days ending the day before $from matches a January
     * selection against December, including both endpoints.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function previousWindow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) round($from->startOfDay()->diffInDays($to->startOfDay())) + 1;

        return [$from->subDays($days), $from->subDay()];
    }

    /**
     * Null when there is no baseline to divide by, so a first-ever period
     * stays blank instead of claiming an infinite increase.
     */
    public static function label(int $current, int $baseline, string $versus): ?string
    {
        if ($baseline === 0) {
            return null;
        }

        $percent = (int) round(($current - $baseline) / abs($baseline) * 100);
        $sign = $percent > 0 ? '+' : '';

        return "{$sign}{$percent}% vs {$versus}";
    }

    public static function sentence(int $current, int $previous, int $lastYear): ?string
    {
        $parts = array_values(array_filter([
            self::label($current, $previous, 'previous'),
            self::label($current, $lastYear, 'last year'),
        ]));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * The two windows behind a comparison sentence, for the selected range.
     */
    public static function hint(CarbonImmutable $from, CarbonImmutable $to): string
    {
        [$previousFrom, $previousTo] = self::previousWindow($from, $to);
        $format = 'd.m.Y';

        return 'Previous is '.$previousFrom->format($format).' to '.$previousTo->format($format)
            .', the same number of days immediately before this range. Last year is '
            .$from->subYear()->format($format).' to '.$to->subYear()->format($format).'.';
    }

    /**
     * The percent sentence, with the window dates on hover.
     */
    public static function described(string $sentence, string $hint): HtmlString
    {
        return HoverHint::text($sentence, $hint);
    }
}
