<?php

namespace App\Services\Receipts\Data;

readonly class ParsedItem
{
    public function __construct(
        public int $lineNo,
        public string $name,
        public float $quantity,
        public int $unitPriceCents,
        public int $totalCents,
        public ?string $vatLabel = null,
        public ?float $vatRate = null,
        public ?int $netUnitPriceCents = null,
        public ?int $netTotalCents = null,
    ) {}
}
