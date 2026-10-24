<?php

namespace App\Jobs;

use App\Enums\PromotionSource;
use App\Enums\ReceiptProvider;
use App\Enums\SyncRunStatus;
use App\Jobs\Concerns\RefreshesGroceryPromotions;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use App\Models\User;
use App\Services\Promotions\FrequentArticles;
use App\Services\Promotions\MaxiPromotion;
use App\Services\Promotions\PromotionMatcher;
use App\Services\Receipts\GroceryAccountAuthenticator;
use App\Services\Receipts\Maxi\MaxiClient;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replaces one Moj Maxi account's promotions and the matches against articles
 * that account buys often. A failed fetch leaves yesterday's rows.
 */
class RefreshMaxiPromotions implements ShouldQueue
{
    use Queueable, RefreshesGroceryPromotions;

    public int $timeout = 300;

    public int $tries = 1;

    public static function overlapKey(int $groceryAccountId): string
    {
        return 'maxi-promotions:'.$groceryAccountId;
    }

    public function __construct(
        private readonly int $groceryAccountId,
        private readonly ?int $syncRunId = null,
    ) {}

    public static function dispatchUnattended(?User $startedBy = null): int
    {
        return self::dispatchAccounts(ReceiptProvider::Maxi, $startedBy);
    }

    public function handle(GroceryAccountAuthenticator $authenticator, MaxiClient $client, PromotionMatcher $matcher): void
    {
        $this->withRefreshLock(fn () => $this->refresh($authenticator, $client, $matcher));
    }

    private function refresh(GroceryAccountAuthenticator $authenticator, MaxiClient $client, PromotionMatcher $matcher): void
    {
        $account = GroceryAccount::find($this->groceryAccountId);

        if ($account === null || $account->provider !== ReceiptProvider::Maxi) {
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'The grocery account no longer exists.');

            return;
        }

        if (! $account->canSyncUnattended()) {
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'This Maxi account cannot sign in on its own.');

            return;
        }

        try {
            $token = $authenticator->unattended($account, $account->provider->client());

            if ($token === null) {
                $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'Moj Maxi promotions could not be refreshed. Sign in again from the grocery account.');

                return;
            }

            $client->registerDevice($token->accessToken, $account->device_uuid);
            $storeId = $client->storeId($token->accessToken, $account->device_uuid);
            $articles = $client->listPromotions($token->accessToken, $account->device_uuid, $storeId);

            $counts = $this->replace($account, $storeId, $articles, $matcher);
            $this->finishRefresh(SyncRunStatus::Succeeded, $this->promotionSummary($counts['promotions'], $counts['matches']), null, $counts);
        } catch (ReceiptAuthException) {
            $account->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ])->save();

            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'The Moj Maxi session expired. Sign in again to refresh promotions.');
        } catch (ReceiptException $e) {
            Log::warning('maxi.promotions.failed', [
                'account' => $account->id,
                'reason' => $e->getMessage(),
            ]);
            $message = $e->getMessage() === MaxiClient::NO_STORE
                ? $e->getMessage()
                : 'Moj Maxi promotions could not be refreshed.';
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', $message);
        } catch (Throwable $e) {
            Log::error('maxi.promotions.errored', [
                'account' => $account->id,
                'exception' => $e,
            ]);
            $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'Moj Maxi promotions could not be refreshed.');
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('maxi.promotions.errored', [
            'account' => $this->groceryAccountId,
            'exception' => $e,
        ]);

        $this->finishRefresh(SyncRunStatus::Failed, 'Refresh failed', 'Promotion refresh failed unexpectedly. Please try again.');
    }

    /**
     * @param  list<array{source: PromotionSource, article: array<string, mixed>}>  $articles
     * @return array{promotions: int, matches: int}
     */
    private function replace(GroceryAccount $account, string $storeId, array $articles, PromotionMatcher $matcher): array
    {
        $frequent = FrequentArticles::forAccount($account);
        $rows = [];
        $seen = [];

        foreach ($articles as $row) {
            $attributes = MaxiPromotion::attributes($row['article'], $account->id, $storeId, $row['source']);

            if ($attributes === null) {
                continue;
            }

            $source = $attributes['source'] instanceof PromotionSource
                ? $attributes['source']->value
                : (string) $attributes['source'];
            $key = $source.'|'.$attributes['shop_variant_id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = $attributes;
        }

        $matches = 0;

        $account->getConnection()->transaction(function () use ($account, $rows, $frequent, $matcher, &$matches): void {
            $account->promotions()->delete();

            foreach ($rows as $attributes) {
                $promotion = GroceryPromotion::query()->create($attributes);
                $matches += $this->recordPromotionMatches($promotion, $frequent, $matcher);
            }

            $account->forceFill([
                'promotions_synced_at' => now(),
            ])->save();
        });

        return ['promotions' => count($rows), 'matches' => $matches];
    }
}
