<?php

namespace App\Services\Raiffeisen;

use FFI;
use RuntimeException;

/**
 * Reproduces RaiOnline's login password hash byte-for-bit.
 *
 * RaiOnline's own JS hashes the password with Argon2i (not Argon2id) using a
 * salt derived from the username, a memory cost (4096 KiB) and 8-byte-minimum
 * salt that fall below libsodium's crypto_pwhash wrapper's enforced minimums
 * and fixed salt length — so this goes through libargon2 directly via FFI
 * instead, which imposes no such floor.
 */
class Argon2iHasher
{
    private const TIME_COST = 3;

    private const MEMORY_COST_KIB = 4096;

    private const PARALLELISM = 1;

    private const HASH_LENGTH = 32;

    private const MIN_SALT_LENGTH = 8;

    public function hash(string $username, string $password): string
    {
        $salt = strtolower($username);

        if (strlen($salt) < self::MIN_SALT_LENGTH) {
            $salt .= str_repeat("\0", self::MIN_SALT_LENGTH - strlen($salt));
        }

        $ffi = FFI::cdef(
            'int argon2i_hash_raw(const uint32_t t_cost, const uint32_t m_cost, '
                .'const uint32_t parallelism, const void *pwd, const size_t pwdlen, '
                .'const void *salt, const size_t saltlen, void *hash, const size_t hashlen);',
            'libargon2.so.1'
        );

        $hashBuf = $ffi->new('unsigned char['.self::HASH_LENGTH.']');

        $result = $ffi->argon2i_hash_raw(
            self::TIME_COST,
            self::MEMORY_COST_KIB,
            self::PARALLELISM,
            $password,
            strlen($password),
            $salt,
            strlen($salt),
            FFI::addr($hashBuf[0]),
            self::HASH_LENGTH
        );

        if ($result !== 0) {
            throw new RuntimeException("argon2i_hash_raw failed with code {$result}");
        }

        $hex = '';
        for ($i = 0; $i < self::HASH_LENGTH; $i++) {
            $hex .= sprintf('%02x', $hashBuf[$i]);
        }

        return $hex;
    }
}
