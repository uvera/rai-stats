<?php

namespace App\Jobs;

use App\Enums\ReceiptProvider;
use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Enums\UserRole;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Promotions\FrequentArticles;
use App\Services\Promotions\MetroCardholder;
use App\Services\Promotions\PromotionMatcher;
use App\Services\Promotions\RankedPromotion;
use App\Services\Receipts\GroceryAccountAuthenticator;
use App\Services\Receipts\Metro\MetroClient;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replaces one Metro account's ranked promotions and the matches against
 * articles that account buys often. A failed fetch leaves yesterday's rows.
 */
class RefreshMetroPromotions implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public static function overlapKey(int $groceryAccountId): string
    {
        return 'metro-promotions:'.$groceryAccountId;
    }

    public function __construct(
        private readonly int $groceryAccountId,
        private readonly ?int $syncRunId = null,
    ) {}

    public static function dispatchUnattended(?User $startedBy = null): int
    {
        $dispatched = 0;

        GroceryAccount::query()
            ->where('provider', ReceiptProvider::Metro)
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

    public function handle(GroceryAccountAuthenticator $authenticator, MetroClient $client, PromotionMatcher $matcher): void
    {
        $lock = Cache::lock(self::overlapKey($this->groceryAccountId), $this->timeout);

        if (! $lock->get()) {
            $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'A promotion refresh for this account is already running.');

            return;
        }

        try {
            $this->refresh($authenticator, $client, $matcher);
        } finally {
            $lock->release();
        }
    }

    private function refresh(GroceryAccountAuthenticator $authenticator, MetroClient $client, PromotionMatcher $matcher): void
    {
        $account = GroceryAccount::find($this->groceryAccountId);

        if ($account === null || $account->provider !== ReceiptProvider::Metro) {
            $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'The grocery account no longer exists.');

            return;
        }

        if (! $account->canSyncUnattended()) {
            $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'This Metro account cannot sign in on its own.');

            return;
        }

        try {
            $token = $authenticator->unattended($account, $account->provider->client());

            if ($token === null) {
                $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'Metro promotions could not be refreshed. Sign in again from the grocery account.');

                return;
            }

            $cardholderId = $client->cardholderId($token->accessToken);
            $location = $cardholderId === null ? null : MetroCardholder::parse($cardholderId);

            if ($cardholderId === null || $location === null) {
                $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'This Metro account has no loyalty card, so promotions cannot be loaded.');

                return;
            }

            $articles = $client->listRankedPromotions(
                $token->accessToken,
                $location['country'],
                $location['store'],
                $cardholderId,
            );

            $counts = $this->replace($account, $cardholderId, $location['store'], $articles, $matcher);
            $this->finish(SyncRunStatus::Succeeded, $this->summary($counts['promotions'], $counts['matches']), null, $counts);
        } catch (ReceiptAuthException) {
            $account->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ])->save();

            $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'The Metro session expired. Sign in again to refresh promotions.');
        } catch (ReceiptException $e) {
            Log::warning('metro.promotions.failed', [
                'account' => $account->id,
                'reason' => $e->getMessage(),
            ]);
            $message = $e->getMessage() === 'Metro promotions request timed out.'
                ? $e->getMessage()
                : 'Metro promotions could not be refreshed.';
            $this->finish(SyncRunStatus::Failed, 'Refresh failed', $message);
        } catch (Throwable $e) {
            Log::error('metro.promotions.errored', [
                'account' => $account->id,
                'exception' => $e,
            ]);
            $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'Metro promotions could not be refreshed.');
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('metro.promotions.errored', [
            'account' => $this->groceryAccountId,
            'exception' => $e,
        ]);

        $this->finish(SyncRunStatus::Failed, 'Refresh failed', 'Promotion refresh failed unexpectedly. Please try again.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $articles
     * @return array{promotions: int, matches: int}
     */
    private function replace(GroceryAccount $account, string $cardholderId, string $store, array $articles, PromotionMatcher $matcher): array
    {
        $frequent = FrequentArticles::forAccount($account);
        $rows = [];
        $seen = [];

        foreach ($articles as $article) {
            $attributes = RankedPromotion::attributes($article, $account->id, $store);

            if ($attributes === null || isset($seen[$attributes['shop_variant_id']])) {
                continue;
            }

            $seen[$attributes['shop_variant_id']] = true;
            $rows[] = $attributes;
        }

        $matches = 0;

        $account->getConnection()->transaction(function () use ($account, $cardholderId, $rows, $frequent, $matcher, &$matches): void {
            $account->promotions()->delete();

            foreach ($rows as $attributes) {
                $promotion = GroceryPromotion::query()->create($attributes);

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
            }

            $account->forceFill([
                'external_id' => $cardholderId,
                'promotions_synced_at' => now(),
            ])->save();
        });

        return ['promotions' => count($rows), 'matches' => $matches];
    }

    private function summary(int $promotions, int $matches): string
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
    private function finish(SyncRunStatus $status, string $summary, ?string $message = null, ?array $details = null): void
    {
        if ($this->syncRunId === null) {
            return;
        }

        SyncRun::query()->find($this->syncRunId)?->finish($status, $summary, $message, $details);
    }
}
