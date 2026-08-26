<?php

namespace Tests\Unit\Support;

use App\Support\DateRange;
use App\Support\DateRangeMerger;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeMergerTest extends TestCase
{
    private function range(string $from, string $to): DateRange
    {
        return new DateRange(new DateTimeImmutable($from), new DateTimeImmutable($to));
    }

    private function assertRangeEquals(string $from, string $to, DateRange $actual): void
    {
        $this->assertSame($from, $actual->from->format('Y-m-d'));
        $this->assertSame($to, $actual->to->format('Y-m-d'));
    }

    public function test_merge_of_disjoint_ranges_keeps_them_separate(): void
    {
        $merged = DateRangeMerger::merge([
            $this->range('2026-01-01', '2026-01-05'),
            $this->range('2026-03-01', '2026-03-05'),
        ]);

        $this->assertCount(2, $merged);
        $this->assertRangeEquals('2026-01-01', '2026-01-05', $merged[0]);
        $this->assertRangeEquals('2026-03-01', '2026-03-05', $merged[1]);
    }

    public function test_merge_of_overlapping_ranges(): void
    {
        $merged = DateRangeMerger::merge([
            $this->range('2026-01-01', '2026-01-10'),
            $this->range('2026-01-05', '2026-01-15'),
        ]);

        $this->assertCount(1, $merged);
        $this->assertRangeEquals('2026-01-01', '2026-01-15', $merged[0]);
    }

    public function test_merge_of_adjacent_ranges_with_no_gap(): void
    {
        $merged = DateRangeMerger::merge([
            $this->range('2026-01-01', '2026-01-10'),
            $this->range('2026-01-11', '2026-01-20'),
        ]);

        $this->assertCount(1, $merged);
        $this->assertRangeEquals('2026-01-01', '2026-01-20', $merged[0]);
    }

    public function test_merge_keeps_ranges_with_a_real_one_day_gap_separate(): void
    {
        $merged = DateRangeMerger::merge([
            $this->range('2026-01-01', '2026-01-10'),
            $this->range('2026-01-12', '2026-01-20'),
        ]);

        $this->assertCount(2, $merged);
    }

    public function test_merge_handles_unsorted_input(): void
    {
        $merged = DateRangeMerger::merge([
            $this->range('2026-03-01', '2026-03-05'),
            $this->range('2026-01-01', '2026-01-05'),
            $this->range('2026-02-01', '2026-02-05'),
        ]);

        $this->assertCount(3, $merged);
        $this->assertRangeEquals('2026-01-01', '2026-01-05', $merged[0]);
        $this->assertRangeEquals('2026-02-01', '2026-02-05', $merged[1]);
        $this->assertRangeEquals('2026-03-01', '2026-03-05', $merged[2]);
    }

    public function test_subtract_with_no_coverage_returns_the_whole_request(): void
    {
        $gaps = DateRangeMerger::subtract($this->range('2026-01-01', '2026-01-31'), []);

        $this->assertCount(1, $gaps);
        $this->assertRangeEquals('2026-01-01', '2026-01-31', $gaps[0]);
    }

    public function test_subtract_when_fully_covered_returns_no_gaps(): void
    {
        $gaps = DateRangeMerger::subtract(
            $this->range('2026-01-10', '2026-01-20'),
            [$this->range('2026-01-01', '2026-01-31')]
        );

        $this->assertCount(0, $gaps);
    }

    public function test_subtract_with_gap_at_the_start(): void
    {
        $gaps = DateRangeMerger::subtract(
            $this->range('2026-01-01', '2026-01-31'),
            [$this->range('2026-01-15', '2026-01-31')]
        );

        $this->assertCount(1, $gaps);
        $this->assertRangeEquals('2026-01-01', '2026-01-14', $gaps[0]);
    }

    public function test_subtract_with_gap_at_the_end(): void
    {
        $gaps = DateRangeMerger::subtract(
            $this->range('2026-01-01', '2026-01-31'),
            [$this->range('2026-01-01', '2026-01-15')]
        );

        $this->assertCount(1, $gaps);
        $this->assertRangeEquals('2026-01-16', '2026-01-31', $gaps[0]);
    }

    public function test_subtract_with_gap_in_the_middle(): void
    {
        $gaps = DateRangeMerger::subtract(
            $this->range('2026-01-01', '2026-01-31'),
            [
                $this->range('2026-01-01', '2026-01-10'),
                $this->range('2026-01-20', '2026-01-31'),
            ]
        );

        $this->assertCount(1, $gaps);
        $this->assertRangeEquals('2026-01-11', '2026-01-19', $gaps[0]);
    }

    public function test_subtract_ignores_coverage_outside_the_requested_range(): void
    {
        $gaps = DateRangeMerger::subtract(
            $this->range('2026-02-01', '2026-02-28'),
            [$this->range('2026-01-01', '2026-01-31')]
        );

        $this->assertCount(1, $gaps);
        $this->assertRangeEquals('2026-02-01', '2026-02-28', $gaps[0]);
    }

    public function test_subtract_with_overlapping_covered_ranges_supplied_unmerged(): void
    {
        $gaps = DateRangeMerger::subtract(
            $this->range('2026-01-01', '2026-01-31'),
            [
                $this->range('2026-01-01', '2026-01-12'),
                $this->range('2026-01-08', '2026-01-20'),
            ]
        );

        $this->assertCount(1, $gaps);
        $this->assertRangeEquals('2026-01-21', '2026-01-31', $gaps[0]);
    }
}
