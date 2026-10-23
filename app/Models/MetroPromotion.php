<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'grocery_account_id', 'store_number', 'shop_variant_id', 'name', 'brand',
    'image_url', 'gross_cents', 'strike_gross_cents', 'starts_on', 'ends_on',
    'fetched_at',
])]
class MetroPromotion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
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
        return $this->hasMany(MetroPromotionMatch::class);
    }
}
