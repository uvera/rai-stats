<?php

namespace App\Models;

use App\Enums\CategorySource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grocery_receipt_id', 'line_no', 'name', 'quantity', 'unit_price_cents',
    'net_unit_price_cents', 'total_cents', 'net_total_cents', 'vat_label', 'vat_rate',
    'product_category_id', 'category_source',
])]
class GroceryReceiptItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'vat_rate' => 'decimal:2',
            'category_source' => CategorySource::class,
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GroceryReceipt::class, 'grocery_receipt_id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
