<?php

namespace App\Services\Receipts\Data;

readonly class ParsedReceipt
{
    /**
     * @param  ParsedItem[]  $items
     * @param  array<int, array{label: string, name: string, rate: float, tax_cents: int}>  $vatLines
     */
    public function __construct(
        public array $items,
        public array $vatLines,
        public ?string $pfrNumber,
        public ?string $pursVl,
        public ?int $totalCents,
        public string $rawText,
    ) {}

    public function itemsTotalCents(): int
    {
        return array_sum(array_map(fn (ParsedItem $i) => $i->totalCents, $this->items));
    }

    /**
     * Whether the parsed line items add up to the receipt's own printed
     * grand total - the cheap integrity check on a text-scraped receipt.
     */
    public function itemsReconcile(): bool
    {
        return $this->totalCents !== null
            && $this->items !== []
            && abs($this->itemsTotalCents() - $this->totalCents) <= 1;
    }
}
