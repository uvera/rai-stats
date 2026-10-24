<?php

namespace Tests\Feature\Support;

use App\Support\GrocerySyncSession;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GrocerySyncSessionTest extends TestCase
{
    public function test_password_is_encrypted_at_rest_and_decrypted_on_take(): void
    {
        $id = GrocerySyncSession::start(1);
        GrocerySyncSession::setPassword($id, 'super-secret');

        $raw = Cache::get("grocery-sync-password:{$id}");
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('super-secret', $raw);

        $this->assertSame('super-secret', GrocerySyncSession::takePassword($id));
        $this->assertNull(GrocerySyncSession::takePassword($id));
    }

    public function test_a_plaintext_cached_password_is_discarded(): void
    {
        $id = GrocerySyncSession::start(1);
        Cache::put("grocery-sync-password:{$id}", 'super-secret', 300);

        $this->assertNull(GrocerySyncSession::takePassword($id));
    }
}
