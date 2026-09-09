<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Ephemeral, cache-backed state for one run of the import wizard: tracks the
 * login job's progress (for Livewire polling) and carries the authenticated
 * session's cookies from the login job to the later data-fetch step.
 *
 * Two secrets pass through here: the Raiffeisen password (its own
 * single-read, short-TTL key, deleted the instant the login job reads it)
 * and the post-2FA session cookie jar (a live authenticated banking
 * session, kept only as long as the wizard is open). The configured cache
 * store is the database, so both would otherwise sit as plaintext in the
 * `cache` table and in every DB backup - they are encrypted at rest here
 * with the app key and decrypted transparently on read.
 */
class RaiffeisenImportSession
{
    private const STATE_TTL_SECONDS = 900;

    private const PASSWORD_TTL_SECONDS = 300;

    public static function start(int $userId): string
    {
        $id = (string) Str::uuid();

        self::setState($id, ['status' => 'pending', 'user_id' => $userId]);

        return $id;
    }

    public static function setPassword(string $sessionId, string $password): void
    {
        Cache::put(self::passwordKey($sessionId), Crypt::encryptString($password), self::PASSWORD_TTL_SECONDS);
    }

    /**
     * Reads and immediately deletes the password - callable exactly once.
     */
    public static function takePassword(string $sessionId): ?string
    {
        $stored = Cache::get(self::passwordKey($sessionId));
        Cache::forget(self::passwordKey($sessionId));

        if ($stored === null) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function setState(string $sessionId, array $data): void
    {
        $current = self::getState($sessionId) ?? [];

        Cache::put(
            self::stateKey($sessionId),
            self::encryptCookies([...$current, ...$data]),
            self::STATE_TTL_SECONDS,
        );
    }

    public static function getState(string $sessionId): ?array
    {
        $state = Cache::get(self::stateKey($sessionId));

        return $state === null ? null : self::decryptCookies($state);
    }

    public static function clear(string $sessionId): void
    {
        Cache::forget(self::stateKey($sessionId));
        Cache::forget(self::passwordKey($sessionId));
    }

    /**
     * The cookie jar is the one secret in the state blob. Store it as an
     * encrypted string; every other key stays readable so the wizard's
     * status polling doesn't pay a decrypt per request.
     */
    private static function encryptCookies(array $state): array
    {
        if (isset($state['cookies']) && is_array($state['cookies'])) {
            $state['cookies'] = Crypt::encryptString(json_encode($state['cookies']));
        }

        return $state;
    }

    private static function decryptCookies(array $state): array
    {
        if (isset($state['cookies']) && is_string($state['cookies'])) {
            try {
                $state['cookies'] = json_decode(Crypt::decryptString($state['cookies']), associative: true);
            } catch (DecryptException) {
                unset($state['cookies']);
            }
        }

        return $state;
    }

    private static function stateKey(string $sessionId): string
    {
        return "raiffeisen-import-state:{$sessionId}";
    }

    private static function passwordKey(string $sessionId): string
    {
        return "raiffeisen-import-password:{$sessionId}";
    }
}
