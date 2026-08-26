<?php

namespace App\Services\Raiffeisen\Data;

readonly class LoginResult
{
    public function __construct(
        public string $ticket,
        public string $requestToken,
        public bool $forceSecondLogin,
        public int $securityUserId,
        public int $generatedSessionId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ticket: $data['Ticket'],
            requestToken: $data['RequestToken'],
            forceSecondLogin: $data['ForceSecondLogin'],
            securityUserId: $data['SecurityUserID'],
            generatedSessionId: $data['GeneratedSessionID'],
        );
    }
}
