<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "Moj Maxi" loyalty account whose receipt history rai-stats syncs. Only
 * the long-lived JWT is stored (encrypted); the password is never persisted,
 * so an admin re-enters it whenever the token is missing or has expired.
 */
#[Fillable(['label', 'email', 'access_token', 'token_expires_at', 'device_uuid', 'user_id', 'last_synced_at'])]
#[Hidden(['access_token'])]
class MaxiAccount extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // A stable per-account device id the Moj Maxi backend expects on
        // every request - generated once, whatever created the row. Must
        // look like an Android ID (16 hex chars); the backend 406s anything
        // else.
        static::creating(function (MaxiAccount $account) {
            if (blank($account->device_uuid)) {
                $account->device_uuid = bin2hex(random_bytes(8));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(MaxiReceipt::class);
    }

    /**
     * The rai-stats user whose bank transactions receipts from this account
     * are matched against.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tokenValid(): bool
    {
        return filled($this->access_token)
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }
}
