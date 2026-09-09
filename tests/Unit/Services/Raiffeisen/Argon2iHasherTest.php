<?php

namespace Tests\Unit\Services\Raiffeisen;

use App\Services\Raiffeisen\Argon2iHasher;
use PHPUnit\Framework\TestCase;

class Argon2iHasherTest extends TestCase
{
    /**
     * Known-answer vector, cross-checked against an independent Argon2i
     * implementation (Python argon2-cffi hash_secret_raw, Type.I,
     * time_cost=3, memory_cost=4096, parallelism=1, hash_len=32,
     * salt = the lowercased username). A wrong hash means a failed login
     * or, after retries, a locked account - so this is pinned exactly.
     */
    public function test_matches_a_known_argon2i_vector(): void
    {
        $hash = (new Argon2iHasher)->hash('myusername', 's3cr3tpass');

        $this->assertSame(
            '70f811d77658307d8813524437469de7a606a2df174f4a12306805db4f2f9f77',
            $hash,
        );
    }

    public function test_is_deterministic_and_hex_encoded(): void
    {
        $hasher = new Argon2iHasher;

        $first = $hasher->hash('someone', 'password');
        $second = $hasher->hash('someone', 'password');

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    public function test_username_is_lowercased_before_it_becomes_the_salt(): void
    {
        $hasher = new Argon2iHasher;

        $this->assertSame(
            $hasher->hash('myusername', 'pw'),
            $hasher->hash('MyUserName', 'pw'),
        );
    }

    public function test_a_short_username_is_padded_to_the_minimum_salt_length(): void
    {
        // "abc" is below the 8-byte floor; it is right-padded with NULs, so
        // it must not collide with a different short username.
        $hasher = new Argon2iHasher;

        $this->assertNotSame(
            $hasher->hash('abc', 'pw'),
            $hasher->hash('abcd', 'pw'),
        );
        $this->assertSame(
            '401e9a3ead6c1284d369deda31285b025c0df82ea6ae3a6e24c0a037e1474870',
            $hasher->hash('abc', 'pw'),
        );
    }
}
