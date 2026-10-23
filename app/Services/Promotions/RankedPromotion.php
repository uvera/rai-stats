<?php

namespace App\Services\Promotions;

use App\Enums\PromotionSource;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * One row of Metro's ranked-promotions feed, reduced to the columns we cache.
 */
class RankedPromotion
{
    /**
     * @param  array<string, mixed>  $article
     * @return array<string, mixed>|null
     */
    public static function attributes(array $article, int $groceryAccountId, string $store): ?array
    {
        $bundle = self::arrayAt($article['bundles'] ?? null, 0);
        $price = self::arrayAt($bundle['fulfillment'] ?? null, 0)['price'] ?? null;
        $price = is_array($price) ? $price : [];
        $shopVariantId = $article['shopVariantId'] ?? null;
        $name = trim((string) ($article['name'] ?? ($bundle['description'] ?? '')));
        $gross = self::cents($price['articleGross'] ?? null);

        if (! is_string($shopVariantId) || $shopVariantId === '' || $name === '' || $gross === null) {
            return null;
        }

        $promotion = is_array($price['promotion'] ?? null) ? $price['promotion'] : [];
        $brand = $bundle['brand'] ?? null;

        return [
            'grocery_account_id' => $groceryAccountId,
            'store_number' => $store,
            'shop_variant_id' => $shopVariantId,
            'source' => PromotionSource::Ranked,
            'name' => $name,
            'brand' => is_string($brand) && $brand !== '' ? $brand : null,
            'image_url' => self::image($article, $bundle),
            'gross_cents' => $gross,
            'strike_gross_cents' => self::cents($price['strikeThroughGross'] ?? null),
            'starts_on' => self::date($promotion['startDate'] ?? null),
            'ends_on' => self::date($promotion['endDate'] ?? null),
            'fetched_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayAt(mixed $list, int $index): array
    {
        if (! is_array($list) || ! is_array($list[$index] ?? null)) {
            return [];
        }

        return $list[$index];
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  array<string, mixed>  $bundle
     */
    private static function image(array $article, array $bundle): ?string
    {
        $url = $article['imageUrl'] ?? ($bundle['images'][0]['urlLarge'] ?? null);

        return is_string($url) && $url !== '' ? $url : null;
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
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
