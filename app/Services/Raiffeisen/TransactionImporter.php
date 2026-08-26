<?php

namespace App\Services\Raiffeisen;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\ImportCoverage;
use App\Models\Transaction as TransactionModel;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction;
use App\Support\DateRange;
use App\Support\DateRangeMerger;

/**
 * Writes fetched Raiffeisen data into the database, de-duplicating on
 * (account_id, dedup_key) and keeping each account's ImportCoverage rows
 * merged and gap-free.
 */
class TransactionImporter
{
    /**
     * @param  Transaction[]  $transactions
     * @return int Rows actually inserted (duplicates are silently skipped).
     */
    public function importTurnover(Account $account, int $importedByUserId, array $transactions): int
    {
        if (empty($transactions)) {
            return 0;
        }

        $rows = array_map(fn (Transaction $dto) => [
            'account_id' => $account->id,
            'user_id' => $importedByUserId,
            'date' => $dto->date->format('Y-m-d H:i:s'),
            'amount_cents' => $dto->amountCents,
            'currency_code' => $dto->currencyCode,
            'place' => $dto->place,
            'reference' => $dto->reference ?: null,
            'description' => $dto->description ?: null,
            'type' => TransactionType::fromRaiffeisen($dto->type)->value,
            'bank_transaction_id' => $dto->bankTransactionId ?: null,
            'dedup_key' => $this->dedupKey($account->id, $dto->bankTransactionId, $dto->date->format('Y-m-d H:i:s'), $dto->amountCents, $dto->place, $dto->description),
            'created_at' => now(),
            'updated_at' => now(),
        ], $transactions);

        return TransactionModel::query()->insertOrIgnore($rows);
    }

    /**
     * @param  ReservedTransaction[]  $reserved
     * @return int Rows actually inserted (duplicates are silently skipped).
     */
    public function importReserved(Account $account, int $importedByUserId, array $reserved): int
    {
        if (empty($reserved)) {
            return 0;
        }

        $rows = array_map(fn (ReservedTransaction $dto) => [
            'account_id' => $account->id,
            'user_id' => $importedByUserId,
            'date' => $dto->date->format('Y-m-d H:i:s'),
            'amount_cents' => $dto->amountCents,
            'currency_code' => $dto->currencyCode,
            'place' => $dto->place,
            'reference' => null,
            'description' => null,
            'type' => TransactionType::Reserved->value,
            'bank_transaction_id' => null,
            // Reserved rows never carry a bank ID - always hash-derived.
            'dedup_key' => $this->dedupKey($account->id, null, $dto->date->format('Y-m-d H:i:s'), $dto->amountCents, $dto->place, ''),
            'created_at' => now(),
            'updated_at' => now(),
        ], $reserved);

        return TransactionModel::query()->insertOrIgnore($rows);
    }

    /**
     * Merges $range into the account's existing coverage, replacing any
     * rows it overlaps or touches with the single merged span.
     */
    public function recordCoverage(Account $account, DateRange $range): void
    {
        $existing = $account->importCoverages()->get()
            ->map(fn (ImportCoverage $c) => new DateRange($c->from_date, $c->to_date))
            ->all();

        $merged = DateRangeMerger::merge([...$existing, $range]);

        $account->importCoverages()->delete();

        foreach ($merged as $coveredRange) {
            $account->importCoverages()->create([
                'from_date' => $coveredRange->from,
                'to_date' => $coveredRange->to,
            ]);
        }
    }

    private function dedupKey(int $accountId, ?string $bankTransactionId, string $date, int $amountCents, string $place, string $description): string
    {
        if (! empty($bankTransactionId)) {
            return $bankTransactionId;
        }

        return hash('sha256', implode('|', [$accountId, $date, $amountCents, $place, $description]));
    }
}
