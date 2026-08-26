<?php

namespace App\Jobs;

use App\Services\Raiffeisen\RaiffeisenClient;
use App\Support\RaiffeisenImportSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Logs into RaiOnline (including the up-to-3-minute mobile push 2FA wait)
 * and fetches the account list, reporting progress via
 * RaiffeisenImportSession for the wizard's Livewire polling. Runs as a
 * queued job specifically so the 2FA wait doesn't tie up a web worker.
 */
class RaiffeisenLoginJob implements ShouldQueue
{
    use Queueable;

    /**
     * Generous ceiling above the client's own 3-minute push timeout, so the
     * queue worker's own timeout never fires first and masks the real error.
     */
    public int $timeout = 240;

    public function __construct(
        private readonly string $importSessionId,
        private readonly string $username,
    ) {}

    public function handle(RaiffeisenClient $client): void
    {
        $password = RaiffeisenImportSession::takePassword($this->importSessionId);

        if ($password === null) {
            RaiffeisenImportSession::setState($this->importSessionId, [
                'status' => 'failed',
                'message' => 'Password expired before the login could run - please try again.',
            ]);

            return;
        }

        try {
            $client->login();

            $loginResult = $client->loginFont($this->username, $password);
            unset($password);

            if ($loginResult->forceSecondLogin) {
                RaiffeisenImportSession::setState($this->importSessionId, ['status' => 'awaiting_push']);

                $push = $client->requestLoginPush($loginResult->ticket, $this->username, timeoutSeconds: 180);
                $client->loginUPPush($loginResult->ticket, $push->pushRequestContent, $loginResult->generatedSessionId);
            }

            $accounts = $client->allAccountBalance();

            RaiffeisenImportSession::setState($this->importSessionId, [
                'status' => 'ready',
                'cookies' => $client->exportCookies(),
                // Plain arrays, not AccountBalance DTOs: Laravel's cache
                // stores unserialize with allowed_classes=false by default
                // (config/cache.php serializable_classes), which silently
                // turns any cached object into __PHP_Incomplete_Class.
                'accounts' => array_map(fn ($a) => [
                    'number' => $a->number,
                    'description' => $a->description,
                    'currency_code' => $a->currencyCode,
                    'currency_code_numeric' => $a->currencyCodeNumeric,
                    'product_core_id' => $a->productCoreId,
                ], $accounts),
            ]);
        } catch (Throwable $e) {
            RaiffeisenImportSession::setState($this->importSessionId, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
