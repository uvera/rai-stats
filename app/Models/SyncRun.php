<?php

namespace App\Models;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Support\SyncRunNotifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable outcome of one grocery sync, bank login, or bank import. The
 * cache sessions still drive live progress; this row is what the report
 * page and the bell notification point at after the run finishes.
 */
#[Fillable([
    'user_id', 'kind', 'status', 'grocery_account_id', 'summary', 'message',
    'details', 'started_at', 'finished_at',
])]
class SyncRun extends Model
{
    use HasFactory;

    public const MESSAGE_LIMIT = 500;

    protected function casts(): array
    {
        return [
            'kind' => SyncKind::class,
            'status' => SyncRunStatus::class,
            'details' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groceryAccount(): BelongsTo
    {
        return $this->belongsTo(GroceryAccount::class);
    }

    public static function open(User $user, SyncKind $kind, ?GroceryAccount $account = null, string $summary = 'Running'): self
    {
        return self::query()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'status' => SyncRunStatus::Running,
            'grocery_account_id' => $account?->id,
            'summary' => $summary,
            'started_at' => now(),
        ]);
    }

    public static function grocerySyncInProgress(int $groceryAccountId): bool
    {
        return self::query()
            ->where('grocery_account_id', $groceryAccountId)
            ->where('kind', SyncKind::GrocerySync)
            ->where('status', SyncRunStatus::Running)
            ->exists();
    }

    public static function promotionRefreshInProgress(int $groceryAccountId): bool
    {
        return self::query()
            ->where('grocery_account_id', $groceryAccountId)
            ->where('kind', SyncKind::PromotionRefresh)
            ->where('status', SyncRunStatus::Running)
            ->exists();
    }

    /**
     * Moves a running report to a terminal state and notifies its owner.
     * A second call is a no-op, so a job's failed() hook can safely repeat it.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function finish(SyncRunStatus $status, string $summary, ?string $message = null, ?array $details = null): void
    {
        if ($this->status !== SyncRunStatus::Running) {
            return;
        }

        $this->forceFill([
            'status' => $status,
            'summary' => $summary,
            'message' => self::clip($message),
            'details' => $details,
            'finished_at' => now(),
        ])->save();

        SyncRunNotifier::send($this);
    }

    public static function clip(?string $message): ?string
    {
        if (blank($message)) {
            return null;
        }

        return mb_substr($message, 0, self::MESSAGE_LIMIT);
    }
}
