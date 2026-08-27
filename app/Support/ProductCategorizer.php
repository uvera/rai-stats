<?php

namespace App\Support;

use App\Models\ProductCategoryRule;

/**
 * Resolves a raw receipt-item name to a product category id via
 * case-insensitive substring matching against product_category_rules -
 * the item-level counterpart of MerchantCategorizer. Rules are loaded once
 * per instance since this runs once per imported receipt or backfill.
 */
readonly class ProductCategorizer
{
    /**
     * @var array<int, ProductCategoryRule>
     */
    private array $rules;

    public function __construct()
    {
        $this->rules = ProductCategoryRule::query()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function categorize(string $itemName): ?int
    {
        $haystack = mb_strtolower($itemName);

        foreach ($this->rules as $rule) {
            if (str_contains($haystack, mb_strtolower($rule->pattern))) {
                return $rule->product_category_id;
            }
        }

        return null;
    }
}
