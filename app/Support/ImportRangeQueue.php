<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * Queues import date ranges for one wizard session. A requested range is
 * clipped to the gaps left by ranges already queued for that account. Rows
 * already stored are left to the importer's (account_id, dedup_key)
 * de-duplication.
 */
class ImportRangeQueue
{
    /**
     * Returns a new list. Callers pass every value in, so a loop over
     * accounts or year halves cannot move the wizard's visible from/to
     * selection.
     *
     * @param  list<array{account_number: string, from: string, to: string}>  $queued
     * @return array{ranges: list<array{account_number: string, from: string, to: string}>, status: 'added'|'trimmed'|'duplicate'}
     */
    public static function add(array $queued, string $accountNumber, string $from, string $to): array
    {
        $requested = new DateRange(new DateTimeImmutable($from), new DateTimeImmutable($to));

        $queuedForAccount = collect($queued)
            ->where('account_number', $accountNumber)
            ->map(fn ($range) => new DateRange(new DateTimeImmutable($range['from']), new DateTimeImmutable($range['to'])))
            ->all();

        $gaps = DateRangeMerger::subtract($requested, $queuedForAccount);

        if ($gaps === []) {
            return ['ranges' => $queued, 'status' => 'duplicate'];
        }

        foreach ($gaps as $gap) {
            $queued[] = [
                'account_number' => $accountNumber,
                'from' => $gap->from->format('Y-m-d'),
                'to' => $gap->to->format('Y-m-d'),
            ];
        }

        $adjusted = count($gaps) !== 1
            || $gaps[0]->from != $requested->from
            || $gaps[0]->to != $requested->to;

        return ['ranges' => $queued, 'status' => $adjusted ? 'trimmed' : 'added'];
    }
}
