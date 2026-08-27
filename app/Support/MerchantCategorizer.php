<?php

namespace App\Support;

use App\Models\MerchantCategoryRule;

/**
 * Resolves a raw transaction `place` string to a category id via
 * case-insensitive substring matching against merchant_category_rules,
 * mirroring the ilike('%bankomat%') heuristic already used in
 * TransactionStats::atmWithdrawalTotalsByCurrency(). Rules are loaded once
 * per instance rather than per lookup, since the rule table is small and
 * this is typically called once per import batch or backfill run.
 */
readonly class MerchantCategorizer
{
    /**
     * @var array<int, MerchantCategoryRule>
     */
    private array $rules;

    public function __construct()
    {
        $this->rules = MerchantCategoryRule::query()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function categorize(string $place): ?int
    {
        $haystack = mb_strtolower($place);

        foreach ($this->rules as $rule) {
            if (str_contains($haystack, mb_strtolower($rule->pattern))) {
                return $rule->category_id;
            }
        }

        return null;
    }
}
