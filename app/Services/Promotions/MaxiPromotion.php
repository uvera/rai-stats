<?php

namespace App\Services\Promotions;

use App\Enums\PromotionSource;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * One article from a Moj Maxi promotion feed, reduced to the columns we cache.
 */
class MaxiPromotion
{
    /**
     * @param  array<string, mixed>  $article
     * @return array<string, mixed>|null
     */
    public static function attributes(array $article, int $groceryAccountId, string $store, PromotionSource $source): ?array
    {
        $articleId = $article['ArticleId'] ?? null;
        $name = trim((string) ($article['ArticleName'] ?? ''));
        $gross = self::cents($article['DiscountPrice'] ?? null);

        if (! is_scalar($articleId) || (string) $articleId === '' || $name === '' || $gross === null) {
            return null;
        }

        $brand = $article['ArticleBrand'] ?? null;
        $image = $article['ArticleImageUrl'] ?? null;

        return [
            'grocery_account_id' => $groceryAccountId,
            'store_number' => $store,
            'shop_variant_id' => (string) $articleId,
            'source' => $source,
            'name' => $name,
            'brand' => is_string($brand) && $brand !== '' ? $brand : null,
            'image_url' => is_string($image) && $image !== '' ? $image : null,
            'gross_cents' => $gross,
            'strike_gross_cents' => self::cents($article['RegularPrice'] ?? null),
            'starts_on' => self::date($article['DateFromForDiscountPrice'] ?? null),
            'ends_on' => self::date($article['DateToForDiscountPrice'] ?? null),
            'fetched_at' => now(),
        ];
    }

    private static function cents(mixed $amount): ?int
    {
        if (! is_numeric($amount)) {
            return null;
        }

        return (int) round(((float) $amount) * 100);
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{8}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Ymd', $value);
        } catch (Throwable) {
            return null;
        }

        if ($date === false || $date->format('Ymd') !== $value) {
            return null;
        }

        return $date->toDateString();
    }
}
