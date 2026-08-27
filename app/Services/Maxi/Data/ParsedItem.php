<?php

namespace App\Services\Maxi\Data;

readonly class ParsedItem
{
    public function __construct(
        public int $lineNo,
        public string $name,
        public float $quantity,
        public int $unitPriceCents,
        public int $totalCents,
        public ?string $vatLabel,
        public ?float $vatRate,
    ) {}
}
