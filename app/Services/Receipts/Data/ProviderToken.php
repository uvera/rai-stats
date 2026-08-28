<?php

namespace App\Services\Receipts\Data;

use App\Services\Receipts\ReceiptException;
use Carbon\CarbonImmutable;

/**
 * A provider's authenticated session: the bearer token, when it expires, and
 * (for providers that support silent renewal, e.g. Metro) the refresh token.
 */
readonly class ProviderToken
{
    public function __construct(
        public string $accessToken,
        public CarbonImmutable $expiresAt,
        public ?string $refreshToken = null,
    ) {}

    /**
     * Builds the token from a bare access token, reading the expiry out of the
     * JWT's own `exp` claim. Moj Maxi issues a plain ~365-day JWT; Metro a
     * ~1-hour one - either way `exp` is authoritative.
     */
    public static function fromAccessToken(string $accessToken, ?string $refreshToken = null): self
    {
        return new self($accessToken, self::expiryFromJwt($accessToken), $refreshToken);
    }

    private static function expiryFromJwt(string $jwt): CarbonImmutable
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new ReceiptException('Malformed access token - expected a JWT.');
        }

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/'), true) ?: '',
            true
        );

        if (! is_array($payload) || ! isset($payload['exp'])) {
            // Fall back to a conservative lifetime if the claim is missing.
            return CarbonImmutable::now()->addMinutes(30);
        }

        return CarbonImmutable::createFromTimestamp((int) $payload['exp']);
    }
}
