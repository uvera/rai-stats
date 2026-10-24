<?php

namespace App\Jobs;

use App\Enums\ReceiptProvider;
use App\Enums\SyncRunStatus;
use App\Jobs\Concerns\RefreshesGroceryPromotions;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
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
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replaces one Metro account's ranked promotions and the matches against
 * articles that account buys often. A failed fetch leaves yesterday's rows.
 */
class RefreshMetroPromotions implements ShouldQueue
{
    use Queueable, RefreshesGroceryPromotions;

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
        return self::dispatchAccounts(ReceiptProvider::Metro, $startedBy);
    }

    public function handle(GroceryAccountAuthenticator $authenticator, MetroClient $client, PromotionMatcher $matcher): void
    {
        $this->withRefreshLock(fn () => $this->refresh($authenticator, $client, $matcher));
    }

    private function refresh(GroceryAccountAuthenticator $authenticator, MetroClient $client, PromotionMatcher $matcher): void
    {
        $account = GroceryAccount::find($this->groceryAccountId);

        if ($account === null || $account->provider !== ReceiptProvider::Metro) {
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'The grocery account no longer exists.');

            return;
        }

        if (! $account->canSyncUnattended()) {
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'This Metro account cannot sign in on its own.');

            return;
        }

        try {
            $token = $authenticator->unattended($account, $account->provider->client());

            if ($token === null) {
                $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'Metro promotions could not be refreshed. Sign in again from the grocery account.');

                return;
            }

            $cardholderId = $client->cardholderId($token->accessToken);
            $location = $cardholderId === null ? null : MetroCardholder::parse($cardholderId);

            if ($cardholderId === null || $location === null) {
                $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'This Metro account has no loyalty card, so promotions cannot be loaded.');

                return;
            }

            $articles = $client->listRankedPromotions(
                $token->accessToken,
                $location['country'],
                $location['store'],
                $cardholderId,
            );

            $counts = $this->replace($account, $cardholderId, $location['store'], $articles, $matcher);
            $this->finishRefresh(SyncRunStatus::Succeeded, $this->promotionSummary($counts['promotions'], $counts['matches']), null, $counts);
        } catch (ReceiptAuthException) {
            $account->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ])->save();

            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'The Metro session expired. Sign in again to refresh promotions.');
        } catch (ReceiptException $e) {
            Log::warning('metro.promotions.failed', [
                'account' => $account->id,
                'reason' => $e->getMessage(),
            ]);
            $message = $e->getMessage() === 'Metro promotions request timed out.'
                ? $e->getMessage()
                : 'Metro promotions could not be refreshed.';
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', $message);
        } catch (Throwable $e) {
            Log::error('metro.promotions.errored', [
                'account' => $account->id,
                'exception' => $e,
            ]);
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'Metro promotions could not be refreshed.');
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('metro.promotions.errored', [
            'account' => $this->groceryAccountId,
            'exception' => $e,
        ]);

        $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'Promotion refresh failed unexpectedly. Please try again.');
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
                $matches += $this->recordPromotionMatches($promotion, $frequent, $matcher);
            }

            $account->forceFill([
                'external_id' => $cardholderId,
                'promotions_synced_at' => now(),
            ])->save();
        });

        return ['promotions' => count($rows), 'matches' => $matches];
    }
}
