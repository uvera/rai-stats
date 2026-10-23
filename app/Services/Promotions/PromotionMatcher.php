<?php

namespace App\Services\Promotions;

/**
 * Whether a receipt line looks like a Metro promotion.
 *
 * A short receipt name (one or two significant tokens) has to show up in
 * full. A longer one matches when at least two tokens do. A lone token
 * shorter than five characters never matches, so "sir" does not pull in
 * every cheese on promotion. Sizes, pack counts, and packaging words
 * (10/1, 500 ml, pet) are ignored, so two products do not match just
 * because they share a number.
 */
class PromotionMatcher
{
    /** @var list<string> */
    private const FILLERS = [
        'ml', 'g', 'kg', 'l', 'kom', 'komad', 'vol',
        'za', 'i', 'sa', 'od', 'na', 'u',
        'the', 'and', 'a',
        'm', 'cm', 'mm', 'w', 'kw', 'pet',
    ];

    public function matches(string $receiptName, string $promotionName): bool
    {
        $receipt = $this->tokens($receiptName);
        $promotion = array_flip($this->tokens($promotionName));

        if ($receipt === []) {
            return false;
        }

        $hits = 0;

        foreach ($receipt as $token) {
            if (isset($promotion[$token])) {
                $hits++;
            }
        }

        if (count($receipt) === 1) {
            return $hits === 1 && mb_strlen($receipt[0]) >= 5;
        }

        if (count($receipt) <= 2) {
            return $hits === count($receipt);
        }

        return $hits >= 2;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $name): array
    {
        $normalized = mb_strtolower($name);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        $fillers = array_flip(self::FILLERS);

        return array_values(array_filter(
            $parts,
            fn (string $token) => ! isset($fillers[$token]) && ! $this->isMeasure($token),
        ));
    }

    private function isMeasure(string $token): bool
    {
        return preg_match('/^\d+(?:ml|kg|g|l|kom|mm|cm|m|kw|w|k|sl|gr)?$/u', $token) === 1;
    }
}
