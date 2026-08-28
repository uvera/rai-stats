<?php

namespace App\Models;

use App\Enums\ReceiptMatchSource;
use App\Enums\ReceiptProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'grocery_account_id', 'provider', 'external_ref', 'pfr_number', 'purs_vl', 'store_name',
    'store_address', 'store_format', 'purchased_at', 'total_cents', 'net_total_cents',
    'currency_code', 'transaction_id', 'match_source', 'raw_text', 'synced_at',
])]
class GroceryReceipt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider' => ReceiptProvider::class,
            'purchased_at' => 'datetime',
            'synced_at' => 'datetime',
            'match_source' => ReceiptMatchSource::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GroceryAccount::class, 'grocery_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GroceryReceiptItem::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function totalAmount(): float
    {
        return $this->total_cents / 100;
    }

    public function pursUrl(): ?string
    {
        return $this->purs_vl ? 'https://suf.purs.gov.rs/v/?vl='.$this->purs_vl : null;
    }
}
