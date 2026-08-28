<?php

namespace App\Jobs;

use App\Models\GroceryAccount;
use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptImporter;
use App\Support\GrocerySyncSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Pulls a grocery account's invoice history and imports every receipt not
 * already stored, whichever provider the account uses. Manual only -
 * dispatched from the "Sync" action or right after an account is added.
 *
 * Auth: reuse the stored access token while it's valid; else silently refresh
 * it (providers that support it); else log in with the stored password, or
 * the one-shot password from the sync session. With none of those the run
 * stops and asks for a password.
 */
class SyncGroceryAccountJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(
        private readonly int $groceryAccountId,
        private readonly string $syncSessionId,
    ) {}

    /**
     * Only one sync per account runs at a time (prevents wasted duplicate
     * downloads on a double click); the import itself is idempotent
     * regardless, so an overlapping job is simply dropped.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->groceryAccountId))->dontRelease()];
    }

    public function handle(ReceiptImporter $importer): void
    {
        $account = GroceryAccount::find($this->groceryAccountId);

        if ($account === null) {
            return;
        }

        $client = $account->provider->client();

        try {
            GrocerySyncSession::setState($this->syncSessionId, ['status' => 'running']);

            $token = $this->resolveToken($account, $client);

            if ($token === null) {
                GrocerySyncSession::setState($this->syncSessionId, [
                    'status' => 'needs_password',
                    'message' => "Enter the {$account->provider->label()} password to sign in again.",
                ]);

                return;
            }

            $this->syncReceipts($account, $client, $importer, $token);
        } catch (ReceiptAuthException) {
            $account->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ])->save();

            GrocerySyncSession::setState($this->syncSessionId, [
                'status' => 'needs_password',
                'message' => "The {$account->provider->label()} session expired - enter the password to sign in again.",
            ]);
        } catch (Throwable $e) {
            GrocerySyncSession::setState($this->syncSessionId, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveToken(GroceryAccount $account, ProviderClient $client): ?ProviderToken
    {
        if ($account->tokenValid()) {
            return new ProviderToken(
                $account->access_token,
                $account->token_expires_at->toImmutable(),
                $account->refresh_token,
            );
        }

        if (($refreshed = $client->refresh($account)) !== null) {
            return $this->persist($account, $refreshed);
        }

        $password = $account->password ?: GrocerySyncSession::takePassword($this->syncSessionId);

        if (blank($password)) {
            return null;
        }

        $token = $client->login($account, $password);
        unset($password);

        return $this->persist($account, $token);
    }

    private function persist(GroceryAccount $account, ProviderToken $token): ProviderToken
    {
        $account->forceFill([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?? $account->refresh_token,
            'token_expires_at' => $token->expiresAt,
        ])->save();

        return $token;
    }

    private function syncReceipts(GroceryAccount $account, ProviderClient $client, ReceiptImporter $importer, ProviderToken $token): void
    {
        $invoices = $client->listInvoices($account, $token);

        $known = $account->receipts()->pluck('external_ref')->all();
        $new = array_values(array_filter(
            $invoices,
            fn (InvoiceSummary $i) => ! in_array($i->externalRef, $known, true)
        ));

        $imported = 0;

        foreach ($new as $summary) {
            $fetched = $client->fetchReceipt($account, $token, $summary);
            $importer->import($account, $summary, $fetched);
            $imported++;

            GrocerySyncSession::setState($this->syncSessionId, [
                'status' => 'running',
                'imported' => $imported,
                'total_new' => count($new),
            ]);
        }

        $account->forceFill(['last_synced_at' => now()])->save();

        GrocerySyncSession::setState($this->syncSessionId, [
            'status' => 'done',
            'imported' => $imported,
            'total_invoices' => count($invoices),
        ]);
    }
}
