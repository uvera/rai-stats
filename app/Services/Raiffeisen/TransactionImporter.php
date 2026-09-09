<?php

namespace App\Services\Raiffeisen;

use App\Enums\CategorySource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction as TransactionModel;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction;
use App\Support\MerchantCategorizer;
use Illuminate\Support\Collection;

/**
 * Writes fetched Raiffeisen data into the database, de-duplicating on
 * (account_id, dedup_key).
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

        $categorizer = new MerchantCategorizer;

        $rows = array_map(function (Transaction $dto) use ($account, $importedByUserId, $categorizer) {
            $categoryId = $categorizer->categorize($dto->place);

            return [
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
                'category_id' => $categoryId,
                'category_source' => $categoryId !== null ? CategorySource::Rule->value : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $transactions);

        return $this->insertChunked($rows);
    }

    /**
     * Reserved funds are a point-in-time snapshot of the account's current
     * pending holds, not history: once a hold settles it reappears as a
     * real posted transaction with a different (bank-assigned) id, so the
     * old reserved row would linger forever and double-count in any query
     * that forgets scopeExcludingReserved. Replace the account's whole
     * reserved set on every import rather than appending to it.
     *
     * @param  ReservedTransaction[]  $reserved
     * @return int Rows actually inserted.
     */
    public function importReserved(Account $account, int $importedByUserId, array $reserved): int
    {
        TransactionModel::query()
            ->where('account_id', $account->id)
            ->where('type', TransactionType::Reserved->value)
            ->delete();

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

        return $this->insertChunked($rows);
    }

    /**
     * Postgres caps a statement at 65535 bind parameters; at ~15 columns
     * per row a single insertOrIgnore() aborts around 4,300 rows, which a
     * multi-month range on an active account can exceed. Chunk the insert
     * and sum the per-chunk insert counts.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertChunked(array $rows): int
    {
        return (int) Collection::make($rows)
            ->chunk(1000)
            ->sum(fn (Collection $chunk) => TransactionModel::query()->insertOrIgnore($chunk->all()));
    }

    private function dedupKey(int $accountId, ?string $bankTransactionId, string $date, int $amountCents, string $place, string $description): string
    {
        if (! empty($bankTransactionId)) {
            return $bankTransactionId;
        }

        return hash('sha256', implode('|', [$accountId, $date, $amountCents, $place, $description]));
    }
}
