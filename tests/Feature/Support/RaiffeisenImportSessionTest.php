<?php

namespace Tests\Feature\Support;

use App\Support\RaiffeisenImportSession;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RaiffeisenImportSessionTest extends TestCase
{
    public function test_password_is_encrypted_at_rest_and_decrypted_on_take(): void
    {
        $id = RaiffeisenImportSession::start(1);
        RaiffeisenImportSession::setPassword($id, 'super-secret');

        $raw = Cache::get("raiffeisen-import-password:{$id}");
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('super-secret', $raw);

        $this->assertSame('super-secret', RaiffeisenImportSession::takePassword($id));
        // Single-read: gone afterwards.
        $this->assertNull(RaiffeisenImportSession::takePassword($id));
    }

    public function test_session_cookies_are_encrypted_at_rest_and_round_trip(): void
    {
        $id = RaiffeisenImportSession::start(1);

        RaiffeisenImportSession::setState($id, [
            'status' => 'ready',
            'cookies' => ['SESSION' => 'abc123', 'XSRF' => 'def456'],
        ]);

        $rawState = Cache::get("raiffeisen-import-state:{$id}");
        $this->assertIsString($rawState['cookies']);
        $this->assertStringNotContainsString('abc123', $rawState['cookies']);
        // Non-secret keys stay readable without a decrypt.
        $this->assertSame('ready', $rawState['status']);

        $state = RaiffeisenImportSession::getState($id);
        $this->assertSame(['SESSION' => 'abc123', 'XSRF' => 'def456'], $state['cookies']);
    }

    public function test_continue_importing_style_state_round_trip_keeps_cookies_intact(): void
    {
        $id = RaiffeisenImportSession::start(1);
        RaiffeisenImportSession::setState($id, ['status' => 'ready', 'cookies' => ['a' => 'b']]);

        // Mirrors ImportTransactions::continueImporting() refreshing the TTL.
        $state = RaiffeisenImportSession::getState($id);
        RaiffeisenImportSession::setState($id, $state);

        $this->assertSame(['a' => 'b'], RaiffeisenImportSession::getState($id)['cookies']);
    }
}
