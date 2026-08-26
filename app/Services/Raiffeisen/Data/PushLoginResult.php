<?php

namespace App\Services\Raiffeisen\Data;

readonly class PushLoginResult
{
    public function __construct(
        public string $status,
        public string $requestId,
        public string $firstStepTicket,
        public string $pushRequestContent,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['Status'],
            requestId: $data['RequestId'],
            firstStepTicket: $data['FirstStepTicket'],
            pushRequestContent: $data['PushRequestContent'],
        );
    }

    public function isApproved(): bool
    {
        return strtoupper($this->status) === 'APPROVED';
    }
}
