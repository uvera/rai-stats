<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A taxonomy for individual purchased products (Dairy, Produce, Snacks...),
 * separate from the merchant-level Category so the finer product granularity
 * doesn't muddy the existing spend-by-merchant-category chart.
 */
#[Fillable(['name', 'color'])]
class ProductCategory extends Model
{
    use HasFactory;

    public function rules(): HasMany
    {
        return $this->hasMany(ProductCategoryRule::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GroceryReceiptItem::class);
    }
}
