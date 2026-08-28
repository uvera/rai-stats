<?php

namespace App\Models;

use App\Enums\CategorySource;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'account_id', 'user_id', 'date', 'amount_cents', 'currency_code', 'place',
    'reference', 'description', 'type', 'bank_transaction_id', 'dedup_key',
    'category_id', 'category_source',
])]
class Transaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'type' => TransactionType::class,
            'category_source' => CategorySource::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The Moj Maxi receipt linked to this transaction, if one has been
     * matched (see App\Services\Receipts\ReceiptTransactionMatcher).
     */
    public function groceryReceipt(): HasOne
    {
        return $this->hasOne(GroceryReceipt::class);
    }

    /**
     * Reserved/pending rows have no stable bank ID and aren't final amounts
     * - every stats query must exclude them to avoid double-counting once
     * the real transaction posts.
     */
    public function scopeExcludingReserved(Builder $query): Builder
    {
        return $query->where('type', '!=', TransactionType::Reserved);
    }
}
