<?php

namespace App\Services\Maxi\Data;

use App\Services\Maxi\MaxiException;
use Carbon\CarbonImmutable;

readonly class MaxiLoginResult
{
    public function __construct(
        public string $accessToken,
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * Builds the result straight from the login response's AccessToken,
     * reading the expiry out of the JWT's own `exp` claim (the Moj Maxi
     * token is a plain HS256 JWT valid ~365 days).
     */
    public static function fromAccessToken(string $accessToken): self
    {
        return new self($accessToken, self::expiryFromJwt($accessToken));
    }

    private static function expiryFromJwt(string $jwt): CarbonImmutable
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new MaxiException('Malformed access token - expected a JWT.');
        }

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/'), true) ?: '',
            true
        );

        if (! is_array($payload) || ! isset($payload['exp'])) {
            // Fall back to the documented ~1-year lifetime if the claim is
            // somehow missing, minus a day of safety margin.
            return CarbonImmutable::now()->addDays(364);
        }

        return CarbonImmutable::createFromTimestamp((int) $payload['exp']);
    }
}
