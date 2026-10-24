<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'number', 'description', 'currency_code', 'currency_code_numeric', 'product_core_id'])]
class Account extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Creates or refreshes the account the bank returned. Ownership is set
     * once, on first import, and never reassigned: a jointly-held account
     * stays with whoever imported it first. Description and product core id
     * are refreshed every import, because a stale product core id makes the
     * turnover fetch return nothing.
     *
     * @param  array{number: string, description: string, currency_code: string, currency_code_numeric: string, product_core_id: string}  $bankAccount
     */
    public static function syncFromBank(array $bankAccount, int $ownerId): self
    {
        $existing = static::query()->where('number', $bankAccount['number'])->first();

        return static::updateOrCreate(
            ['number' => $bankAccount['number']],
            [
                'user_id' => $existing?->user_id ?? $ownerId,
                'description' => $bankAccount['description'],
                'currency_code' => $bankAccount['currency_code'],
                'currency_code_numeric' => $bankAccount['currency_code_numeric'],
                'product_core_id' => $bankAccount['product_core_id'],
            ],
        );
    }
}
