<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * An inclusive, whole-day date range (both endpoints are dates, not
 * datetimes - time-of-day is stripped on construction).
 */
readonly class DateRange
{
    public DateTimeImmutable $from;

    public DateTimeImmutable $to;

    public function __construct(DateTimeInterface $from, DateTimeInterface $to)
    {
        $from = DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $to = DateTimeImmutable::createFromInterface($to)->setTime(0, 0);

        if ($from > $to) {
            throw new InvalidArgumentException('DateRange "from" must not be after "to"');
        }

        $this->from = $from;
        $this->to = $to;
    }

    /**
     * True if the ranges share a day, or sit on consecutive days with no gap
     * between them - two such ranges describe one contiguous covered span.
     */
    public function touches(self $other): bool
    {
        $oneDayBefore = $this->from->modify('-1 day');
        $oneDayAfter = $this->to->modify('+1 day');

        return $other->to >= $oneDayBefore && $other->from <= $oneDayAfter;
    }

    public function contains(self $other): bool
    {
        return $this->from <= $other->from && $this->to >= $other->to;
    }

    /**
     * True if the ranges share at least one day (unlike touches(), adjacent
     * but non-overlapping ranges don't count).
     */
    public function overlaps(self $other): bool
    {
        return $this->from <= $other->to && $this->to >= $other->from;
    }

    public function merge(self $other): self
    {
        return new self(
            min($this->from, $other->from),
            max($this->to, $other->to),
        );
    }
}
