<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Ephemeral, cache-backed state for one run of a Moj Maxi sync: carries the
 * (optional) password from the Filament action to the queued job, and the
 * job's progress back for the UI.
 *
 * The password only ever lives in its own single-read, short-TTL key,
 * separate from the state, and is deleted the instant the job reads it -
 * mirroring RaiffeisenImportSession.
 */
class MaxiSyncSession
{
    private const STATE_TTL_SECONDS = 900;

    private const PASSWORD_TTL_SECONDS = 300;

    public static function start(int $maxiAccountId): string
    {
        $id = (string) Str::uuid();

        self::setState($id, ['status' => 'pending', 'maxi_account_id' => $maxiAccountId]);

        return $id;
    }

    public static function setPassword(string $sessionId, string $password): void
    {
        Cache::put(self::passwordKey($sessionId), $password, self::PASSWORD_TTL_SECONDS);
    }

    /**
     * Reads and immediately deletes the password - callable exactly once.
     */
    public static function takePassword(string $sessionId): ?string
    {
        $password = Cache::get(self::passwordKey($sessionId));
        Cache::forget(self::passwordKey($sessionId));

        return $password;
    }

    public static function setState(string $sessionId, array $data): void
    {
        $current = self::getState($sessionId) ?? [];
        Cache::put(self::stateKey($sessionId), [...$current, ...$data], self::STATE_TTL_SECONDS);
    }

    public static function getState(string $sessionId): ?array
    {
        return Cache::get(self::stateKey($sessionId));
    }

    public static function clear(string $sessionId): void
    {
        Cache::forget(self::stateKey($sessionId));
        Cache::forget(self::passwordKey($sessionId));
    }

    private static function stateKey(string $sessionId): string
    {
        return "maxi-sync-state:{$sessionId}";
    }

    private static function passwordKey(string $sessionId): string
    {
        return "maxi-sync-password:{$sessionId}";
    }
}
