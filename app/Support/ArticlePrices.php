<?php

namespace App\Support;

use App\Enums\ReceiptProvider;
use App\Models\GroceryReceiptItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Unit prices for one receipt-line name, across every imported grocery receipt.
 *
 * The same spelling is one article. A different spelling is a different
 * article — there is no aliasing. Unit price stays as printed (per piece,
 * or per kilogram for weighed lines).
 */
class ArticlePrices
{
    /**
     * One row per article name. The provider filter is applied before grouping,
     * so an article bought at both Maxi and Metro stays one row unless filtered.
     *
     * @return Builder<GroceryReceiptItem>
     */
    public static function aggregateQuery(?ReceiptProvider $provider = null): Builder
    {
        return GroceryReceiptItem::query()
            ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
            ->when($provider !== null, fn (Builder $query) => $query->where('grocery_receipts.provider', $provider->value))
            ->selectRaw('MIN(grocery_receipt_items.id) as id')
            ->selectRaw('grocery_receipt_items.name as name')
            ->selectRaw('COUNT(*) as times_bought')
            ->selectRaw('MIN(grocery_receipt_items.unit_price_cents) as min_unit_price_cents')
            ->selectRaw('MAX(grocery_receipt_items.unit_price_cents) as max_unit_price_cents')
            ->selectRaw('CAST(ROUND(AVG(grocery_receipt_items.unit_price_cents)) AS bigint) as avg_unit_price_cents')
            ->selectRaw('(ARRAY_AGG(grocery_receipt_items.unit_price_cents ORDER BY grocery_receipts.purchased_at DESC, grocery_receipt_items.id DESC))[1] as last_unit_price_cents')
            ->selectRaw('MAX(grocery_receipts.purchased_at) as last_purchased_at')
            ->selectRaw("STRING_AGG(DISTINCT grocery_receipts.provider, ',' ORDER BY grocery_receipts.provider) as providers")
            ->groupBy('grocery_receipt_items.name');
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     times_bought: int,
     *     min_unit_price_cents: int,
     *     max_unit_price_cents: int,
     *     avg_unit_price_cents: int,
     *     last_unit_price_cents: int,
     *     raise_since_cheapest_percent: float|null,
     *     last_purchased_at: CarbonImmutable,
     *     providers: array<int, string>,
     * }>
     */
    public static function summaries(?ReceiptProvider $provider = null): array
    {
        return self::aggregateQuery($provider)
            ->orderBy('name')
            ->get()
            ->map(fn (GroceryReceiptItem $row) => self::mapSummary($row))
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     times_bought: int,
     *     min_unit_price_cents: int,
     *     max_unit_price_cents: int,
     *     avg_unit_price_cents: int,
     *     last_unit_price_cents: int,
     *     raise_since_cheapest_percent: float|null,
     *     last_purchased_at: CarbonImmutable,
     *     providers: array<int, string>,
     * }|null
     */
    public static function summary(string $name): ?array
    {
        $row = self::aggregateQuery()
            ->where('grocery_receipt_items.name', $name)
            ->first();

        return $row === null ? null : self::mapSummary($row);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2).' RSD';
    }

    /**
     * How far the latest unit price sits above the cheapest one, in percent.
     * Null when the cheapest price is zero and the ratio is undefined.
     */
    public static function raiseSinceCheapest(int $cheapestCents, int $lastCents): ?float
    {
        if ($cheapestCents <= 0) {
            return null;
        }

        return round(($lastCents - $cheapestCents) / $cheapestCents * 100, 1);
    }

    public static function formatRaise(?float $percent): string
    {
        if ($percent === null) {
            return '—';
        }

        return ($percent > 0 ? '+' : '').number_format($percent, 1).'%';
    }

    /**
     * Every purchase of this exact name, newest first. Ties on purchased_at
     * break toward the higher item id, matching last_unit_price_cents.
     *
     * @return Builder<GroceryReceiptItem>
     */
    public static function history(string $name): Builder
    {
        return GroceryReceiptItem::query()
            ->with('receipt')
            ->select('grocery_receipt_items.*')
            ->join('grocery_receipts', 'grocery_receipts.id', '=', 'grocery_receipt_items.grocery_receipt_id')
            ->where('grocery_receipt_items.name', $name)
            ->orderByDesc('grocery_receipts.purchased_at')
            ->orderByDesc('grocery_receipt_items.id');
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     times_bought: int,
     *     min_unit_price_cents: int,
     *     max_unit_price_cents: int,
     *     avg_unit_price_cents: int,
     *     last_unit_price_cents: int,
     *     raise_since_cheapest_percent: float|null,
     *     last_purchased_at: CarbonImmutable,
     *     providers: array<int, string>,
     * }
     */
    private static function mapSummary(GroceryReceiptItem $row): array
    {
        $min = (int) $row->min_unit_price_cents;
        $last = (int) $row->last_unit_price_cents;

        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'times_bought' => (int) $row->times_bought,
            'min_unit_price_cents' => $min,
            'max_unit_price_cents' => (int) $row->max_unit_price_cents,
            'avg_unit_price_cents' => (int) $row->avg_unit_price_cents,
            'last_unit_price_cents' => $last,
            'raise_since_cheapest_percent' => self::raiseSinceCheapest($min, $last),
            'last_purchased_at' => CarbonImmutable::parse($row->last_purchased_at),
            'providers' => collect(explode(',', (string) $row->providers))
                ->filter()
                ->map(fn (string $provider) => ReceiptProvider::from($provider)->label())
                ->values()
                ->all(),
        ];
    }
}
