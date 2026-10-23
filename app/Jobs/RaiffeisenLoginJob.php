<?php

namespace App\Jobs;

use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Services\Raiffeisen\RaiffeisenException;
use App\Support\RaiffeisenImportSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
        private readonly ?int $syncRunId = null,
    ) {}

    public function handle(RaiffeisenClient $client): void
    {
        $password = RaiffeisenImportSession::takePassword($this->importSessionId);

        if ($password === null) {
            $this->fail('Password expired before the login could run - please try again.');

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

            // A successful login is not a report — the import that follows is.
            if ($this->syncRunId !== null) {
                SyncRun::query()->whereKey($this->syncRunId)->delete();
            }

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
        } catch (RaiffeisenException $e) {
            // Thrown by our own client for expected conditions (bad
            // credentials, 2FA timeout, ...) - safe to show.
            Log::warning('raiffeisen.login.failed', [
                'session' => $this->importSessionId,
                'reason' => $e->getMessage(),
            ]);
            $this->fail(SyncRun::clip($e->getMessage()) ?? 'Login failed.');
        } catch (Throwable $e) {
            // Anything else is unexpected: log it in full, show nothing
            // that might carry an upstream response body.
            Log::error('raiffeisen.login.errored', [
                'session' => $this->importSessionId,
                'exception' => $e,
            ]);
            $this->fail('Login failed unexpectedly. Please try again - if it keeps happening, check the application logs.');
        }
    }

    /**
     * Reached only if handle() itself throws (rather than the inner catches
     * handling it) or the job times out / is released - without this the
     * session state would stay 'pending' and the wizard would poll forever
     * (until poll()'s own deadline guard trips).
     */
    public function failed(?Throwable $e): void
    {
        Log::error('raiffeisen.login.errored', [
            'session' => $this->importSessionId,
            'exception' => $e,
        ]);

        $this->fail('Login failed unexpectedly. Please try again - if it keeps happening, check the application logs.');
    }

    private function fail(string $message): void
    {
        RaiffeisenImportSession::setState($this->importSessionId, [
            'status' => 'failed',
            'message' => $message,
        ]);

        if ($this->syncRunId === null) {
            return;
        }

        SyncRun::query()->find($this->syncRunId)?->finish(
            SyncRunStatus::Failed,
            'Login failed',
            $message,
        );
    }
}
