<?php

namespace App\Models;

use App\Enums\ReceiptProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grocery loyalty account (Moj Maxi, Metro, ...) whose receipt history
 * rai-stats syncs. The access token, refresh token and password are all
 * stored encrypted - these are low-value accounts, so keeping the password
 * lets a sync re-authenticate unattended.
 */
#[Fillable([
    'provider', 'label', 'email', 'password', 'access_token', 'refresh_token',
    'token_expires_at', 'device_uuid', 'user_id', 'external_id', 'last_synced_at',
])]
#[Hidden(['password', 'access_token', 'refresh_token'])]
class GroceryAccount extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // A stable per-account device id the Moj Maxi backend expects on
        // every request - generated once, whatever created the row. Must
        // look like an Android ID (16 hex chars); the backend 406s anything
        // else. Metro ignores it but it is harmless to keep.
        static::creating(function (GroceryAccount $account) {
            if (blank($account->device_uuid)) {
                $account->device_uuid = bin2hex(random_bytes(8));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => ReceiptProvider::class,
            'password' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GroceryReceipt::class);
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

    /**
     * Whether a sync can run without prompting: a live token, a refresh token
     * to renew it, or a stored password to log in again.
     */
    public function canSyncUnattended(): bool
    {
        return $this->tokenValid() || filled($this->refresh_token) || filled($this->password);
    }
}
