<?php

namespace App\Services\Promotions;

use App\Enums\PromotionSource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Personalized Moj Maxi rows whose promo price differs from the tri-weekly
 * catalog row for the same article.
 */
class PersonalCatalogComparison
{
    public static function constrain(Builder $query, string $comparison): Builder
    {
        $operator = $comparison === 'cheaper' ? '<' : '>';

        return $query
            ->where('grocery_promotions.source', PromotionSource::Directed->value)
            ->whereExists(function ($catalog) use ($operator): void {
                $catalog->selectRaw('1')
                    ->from('grocery_promotions as catalog')
                    ->whereColumn('catalog.grocery_account_id', 'grocery_promotions.grocery_account_id')
                    ->whereColumn('catalog.shop_variant_id', 'grocery_promotions.shop_variant_id')
                    ->where('catalog.source', PromotionSource::Triweekly->value)
                    ->whereColumn('grocery_promotions.gross_cents', $operator, 'catalog.gross_cents');
            });
    }
}
