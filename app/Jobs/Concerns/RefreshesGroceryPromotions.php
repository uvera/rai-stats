<?php

namespace App\Jobs\Concerns;

use App\Enums\ReceiptProvider;
use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Enums\UserRole;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Promotions\PromotionMatcher;
use Illuminate\Support\Facades\Cache;

/**
 * The dispatch, overlap lock, match insert, and sync-run reporting shared
 * by the Maxi and Metro promotion refresh jobs. Each job keeps its own
 * fetch step.
 */
trait RefreshesGroceryPromotions
{
    abstract public static function overlapKey(int $groceryAccountId): string;

    public static function dispatchAccounts(ReceiptProvider $provider, ?User $startedBy): int
    {
        $dispatched = 0;

        GroceryAccount::query()
            ->where('provider', $provider)
            ->each(function (GroceryAccount $account) use (&$dispatched, $startedBy): void {
                if (! $account->canSyncUnattended()) {
                    return;
                }

                $owner = self::reportOwner($account, $startedBy);

                if ($owner !== null && SyncRun::promotionRefreshInProgress($account->id)) {
                    SyncRun::open($owner, SyncKind::PromotionRefresh, $account, 'Refreshing promotions')
                        ->finish(SyncRunStatus::Failed, 'Refresh failed', 'A promotion refresh for this account is already running.');

                    return;
                }

                $runId = $owner === null
                    ? null
                    : SyncRun::open($owner, SyncKind::PromotionRefresh, $account, 'Refreshing promotions')->id;

                self::dispatch($account->id, $runId);
                $dispatched++;
            });

        return $dispatched;
    }

    private static function reportOwner(GroceryAccount $account, ?User $startedBy): ?User
    {
        if ($startedBy !== null) {
            return $startedBy;
        }

        if ($account->user !== null) {
            return $account->user;
        }

        return User::query()->where('role', UserRole::Admin)->orderBy('id')->first();
    }

    protected function withRefreshLock(callable $refresh): void
    {
        $lock = Cache::lock(static::overlapKey($this->groceryAccountId), $this->timeout);

        if (! $lock->get()) {
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'A promotion refresh for this account is already running.');

            return;
        }

        try {
            $refresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  iterable<int, object>  $frequent
     */
    protected function recordPromotionMatches(GroceryPromotion $promotion, iterable $frequent, PromotionMatcher $matcher): int
    {
        $matches = 0;

        foreach ($frequent as $bought) {
            if (! $matcher->matches($bought->name, $promotion->name)) {
                continue;
            }

            $promotion->matches()->create([
                'article_name' => $bought->name,
                'times_bought' => (int) $bought->times_bought,
                'last_purchased_at' => $bought->last_purchased_at,
                'last_unit_price_cents' => (int) $bought->last_unit_price_cents,
            ]);
            $matches++;
        }

        return $matches;
    }

    protected function promotionSummary(int $promotions, int $matches): string
    {
        $promotionLabel = $promotions === 1 ? '1 promotion' : "{$promotions} promotions";

        if ($matches === 0) {
            return "{$promotionLabel}, none match articles you buy";
        }

        if ($matches === 1) {
            return "{$promotionLabel}, 1 matches an article you buy";
        }

        return "{$promotionLabel}, {$matches} match articles you buy";
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    protected function finishRefresh(SyncRunStatus $status, string $summary, ?string $message = null, ?array $details = null): void
    {
        if ($this->syncRunId === null) {
            return;
        }

        SyncRun::query()->find($this->syncRunId)?->finish($status, $summary, $message, $details);
    }
}
