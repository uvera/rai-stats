<?php

namespace App\Models;

use App\Enums\PromotionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'grocery_account_id', 'store_number', 'shop_variant_id', 'source', 'name', 'brand',
    'image_url', 'gross_cents', 'strike_gross_cents', 'starts_on', 'ends_on',
    'fetched_at',
])]
class GroceryPromotion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => PromotionSource::class,
            'gross_cents' => 'integer',
            'strike_gross_cents' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'fetched_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GroceryAccount::class, 'grocery_account_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GroceryPromotionMatch::class);
    }

    /**
     * Promo price of the tri-weekly catalog row for this same article, when
     * this row is a personalized offer.
     */
    public function triweeklyGrossCents(): ?int
    {
        if ($this->source !== PromotionSource::Directed) {
            return null;
        }

        $cents = self::query()
            ->where('grocery_account_id', $this->grocery_account_id)
            ->where('shop_variant_id', $this->shop_variant_id)
            ->where('source', PromotionSource::Triweekly)
            ->value('gross_cents');

        return $cents === null ? null : (int) $cents;
    }
}
