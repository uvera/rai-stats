<?php

namespace App\Jobs;

use App\Models\MaxiAccount;
use App\Services\Maxi\Data\InvoiceSummary;
use App\Services\Maxi\MaxiAuthException;
use App\Services\Maxi\MaxiClient;
use App\Services\Maxi\ReceiptImporter;
use App\Support\MaxiSyncSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Pulls a Moj Maxi account's invoice history and imports every receipt not
 * already stored. Manual only - dispatched from the "Sync" action or right
 * after an account is added.
 *
 * Auth: reuse the stored JWT while it's valid; otherwise consume the
 * one-shot password from the sync session, log in, and persist a fresh
 * encrypted token. With neither, the run stops and asks for a password.
 */
class SyncMaxiAccountJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(
        private readonly int $maxiAccountId,
        private readonly string $syncSessionId,
    ) {}

    /**
     * Only one sync per account runs at a time (prevents wasted duplicate
     * PDF downloads on a double click); the import itself is idempotent
     * regardless, so an overlapping job is simply dropped.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->maxiAccountId))->dontRelease()];
    }

    public function handle(MaxiClient $client, ReceiptImporter $importer): void
    {
        $account = MaxiAccount::find($this->maxiAccountId);

        if ($account === null) {
            return;
        }

        try {
            MaxiSyncSession::setState($this->syncSessionId, ['status' => 'running']);

            $token = $this->resolveToken($account, $client);

            if ($token === null) {
                MaxiSyncSession::setState($this->syncSessionId, [
                    'status' => 'needs_password',
                    'message' => 'Enter the Moj Maxi password to sign in again.',
                ]);

                return;
            }

            $this->syncReceipts($account, $client, $importer, $token);
        } catch (MaxiAuthException $e) {
            $account->forceFill(['access_token' => null, 'token_expires_at' => null])->save();

            MaxiSyncSession::setState($this->syncSessionId, [
                'status' => 'needs_password',
                'message' => 'The Moj Maxi session expired - enter the password to sign in again.',
            ]);
        } catch (Throwable $e) {
            MaxiSyncSession::setState($this->syncSessionId, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveToken(MaxiAccount $account, MaxiClient $client): ?string
    {
        if ($account->tokenValid()) {
            return $account->access_token;
        }

        $password = MaxiSyncSession::takePassword($this->syncSessionId);

        if ($password === null) {
            return null;
        }

        $result = $client->login($account->email, $password, $account->device_uuid);
        unset($password);

        $account->forceFill([
            'access_token' => $result->accessToken,
            'token_expires_at' => $result->expiresAt,
        ])->save();

        return $result->accessToken;
    }

    private function syncReceipts(MaxiAccount $account, MaxiClient $client, ReceiptImporter $importer, string $token): void
    {
        // The invoice endpoint 406s unless this device has been registered
        // against the account at least once - do it every sync (idempotent).
        $client->registerDevice($token, $account->device_uuid);

        $invoices = $client->listInvoices($token, $account->device_uuid);

        $knownHashes = $account->receipts()->pluck('invoice_hash')->all();
        $new = array_filter($invoices, fn (InvoiceSummary $i) => ! in_array($i->invoiceHash, $knownHashes, true));

        $imported = 0;

        foreach ($new as $summary) {
            $pdf = $client->downloadReceipt($summary->pdfUrl, $account->device_uuid);
            $importer->import($account, $summary, $pdf);
            $imported++;

            MaxiSyncSession::setState($this->syncSessionId, [
                'status' => 'running',
                'imported' => $imported,
                'total_new' => count($new),
            ]);
        }

        $account->forceFill(['last_synced_at' => now()])->save();

        MaxiSyncSession::setState($this->syncSessionId, [
            'status' => 'done',
            'imported' => $imported,
            'total_invoices' => count($invoices),
        ]);
    }
}
