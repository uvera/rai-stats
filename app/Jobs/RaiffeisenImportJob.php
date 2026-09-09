<?php

namespace App\Jobs;

use App\Models\Account;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Services\Raiffeisen\TransactionImporter;
use App\Support\DateRange;
use App\Support\DateRangeMerger;
use App\Support\RaiffeisenImportSession;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches turnover for the wizard's queued ranges and writes it to the
 * database, one account at a time, reporting progress through
 * RaiffeisenImportSession for the wizard's Livewire polling.
 *
 * A queued job for the same reason as RaiffeisenLoginJob: a multi-account
 * year import is dozens of sequential bank round-trips, which must not run
 * inside (and time out) a web request. Each account's writes are their own
 * transaction and a per-account failure is recorded and skipped rather than
 * aborting the whole run.
 */
class RaiffeisenImportJob implements ShouldQueue
{
    use Queueable;

    /** Comfortably above a realistic multi-account year import. */
    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<int, array{account_number: string, from: string, to: string}>  $queuedRanges
     */
    public function __construct(
        private readonly string $importSessionId,
        private readonly int $userId,
        private readonly array $queuedRanges,
    ) {}

    public function handle(): void
    {
        $state = RaiffeisenImportSession::getState($this->importSessionId);
        $cookies = $state['cookies'] ?? null;

        if (empty($cookies)) {
            RaiffeisenImportSession::setState($this->importSessionId, [
                'status' => 'failed',
                'message' => 'The login session expired before the import could run - please start over.',
            ]);

            return;
        }

        $this->import($this->client($cookies));
    }

    /**
     * Seam for tests to inject a mocked client.
     *
     * @param  array<string, mixed>  $cookies
     */
    protected function client(array $cookies): RaiffeisenClient
    {
        return RaiffeisenClient::withCookies($cookies);
    }

    private function import(RaiffeisenClient $client): void
    {
        $importer = new TransactionImporter;
        $results = [];

        foreach (collect($this->queuedRanges)->groupBy('account_number') as $accountNumber => $ranges) {
            $results[] = $this->importAccount($client, $importer, (string) $accountNumber, $ranges->all());

            RaiffeisenImportSession::setState($this->importSessionId, [
                'status' => 'importing',
                'import_results' => $results,
            ]);
        }

        RaiffeisenImportSession::setState($this->importSessionId, [
            'status' => 'done',
            'import_results' => $results,
        ]);
    }

    /**
     * @param  array<int, array{account_number: string, from: string, to: string}>  $ranges
     * @return array{account_number: string, description: string, inserted: int, failed: bool}
     */
    private function importAccount(RaiffeisenClient $client, TransactionImporter $importer, string $accountNumber, array $ranges): array
    {
        // Scoped to the importing user - a tampered queuedRanges entry can't
        // drive an import against someone else's account row.
        $account = Account::query()
            ->where('number', $accountNumber)
            ->where('user_id', $this->userId)
            ->first();

        if ($account === null) {
            Log::warning('raiffeisen.import.unknown_account', [
                'session' => $this->importSessionId,
                'account' => $accountNumber,
            ]);

            return ['account_number' => $accountNumber, 'description' => $accountNumber, 'inserted' => 0, 'failed' => true];
        }

        $mergedRanges = DateRangeMerger::merge(
            array_map(fn ($r) => new DateRange(new DateTimeImmutable($r['from']), new DateTimeImmutable($r['to'])), $ranges)
        );

        try {
            $inserted = 0;

            foreach ($mergedRanges as $range) {
                $transactions = $client->transactionalAccountTurnover(
                    $account->product_core_id,
                    $accountNumber,
                    $account->currency_code_numeric,
                    $range->from->format('d.m.Y'),
                    $range->to->format('d.m.Y'),
                );

                $inserted += DB::transaction(fn () => $importer->importTurnover($account, $this->userId, $transactions));
            }

            // Reserved funds are a current per-account snapshot, unrelated to
            // the historical ranges - fetch once, after them.
            $reserved = $client->transactionalAccountReservedFunds($accountNumber);
            DB::transaction(fn () => $importer->importReserved($account, $this->userId, $reserved));

            return ['account_number' => $accountNumber, 'description' => $account->description, 'inserted' => $inserted, 'failed' => false];
        } catch (Throwable $e) {
            Log::error('raiffeisen.import.account_failed', [
                'session' => $this->importSessionId,
                'account' => $accountNumber,
                'exception' => $e,
            ]);

            return ['account_number' => $accountNumber, 'description' => $account->description, 'inserted' => 0, 'failed' => true];
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('raiffeisen.import.errored', [
            'session' => $this->importSessionId,
            'exception' => $e,
        ]);

        RaiffeisenImportSession::setState($this->importSessionId, [
            'status' => 'failed',
            'message' => 'The import failed unexpectedly. Please try again - if it keeps happening, check the application logs.',
        ]);
    }
}
