<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grocery_promotion_id', 'article_name', 'times_bought', 'last_purchased_at',
    'last_unit_price_cents',
])]
class GroceryPromotionMatch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'times_bought' => 'integer',
            'last_purchased_at' => 'datetime',
            'last_unit_price_cents' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(GroceryPromotion::class, 'grocery_promotion_id');
    }
}
