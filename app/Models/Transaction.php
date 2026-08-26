<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id', 'user_id', 'date', 'amount_cents', 'currency_code', 'place',
    'reference', 'description', 'type', 'bank_transaction_id', 'dedup_key',
])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'type' => TransactionType::class,
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
