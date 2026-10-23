<?php

namespace Tests\Feature\Jobs;

use App\Enums\PromotionSource;
use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Jobs\RefreshMaxiPromotions;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Promotions\PromotionMatcher;
use App\Services\Receipts\GroceryAccountAuthenticator;
use App\Services\Receipts\Maxi\MaxiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RefreshMaxiPromotionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_personal_and_triweekly_prices_and_matches_frequent_articles(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->withValidToken()->create([
            'user_id' => $user->id,
            'external_id' => null,
        ]);
        $other = GroceryAccount::factory()->metro()->create();

        GroceryPromotion::factory()->for($account, 'account')->create([
            'shop_variant_id' => 'OLD',
            'name' => 'Gone',
        ]);
        $kept = GroceryPromotion::factory()->for($other, 'account')->create([
            'shop_variant_id' => 'KEEP',
            'name' => 'Other account',
        ]);

        $this->purchase($account, 'COLLA PARMIGANO REGGIANO 200G', now()->subDays(20), 50000);
        $this->purchase($account, 'COLLA PARMIGANO REGGIANO 200G', now()->subDays(1), 48000);

        $this->fakeMaxiClient([
            new Response(200, [], json_encode(['Status' => true])),
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['StoreId' => 213217]])),
            new Response(200, [], json_encode([
                'Status' => true,
                'Data' => [
                    'Articles' => [
                        $this->article('111', 'COLLA Tvrdi sir parmigano reggiano 200 g', 713.99, '20261111'),
                        $this->article('222', 'PALMOLIVE Gel za tusiranje 500 ml', 199.99, '20261028'),
                    ],
                    'RecordsPerPage' => 20,
                    'TotalRecords' => 2,
                ],
            ])),
            $this->emptyFeed(),
            $this->emptyFeed(),
            $this->emptyFeed(),
            $this->emptyFeed(),
            new Response(200, [], json_encode([
                'Status' => true,
                'Data' => [[
                    'Articles' => [
                        $this->article('111', 'COLLA Tvrdi sir parmigano reggiano 200 g', 678, '20261028'),
                    ],
                ]],
            ])),
        ]);

        $run = $this->refresh($account);

        $account->refresh();
        $this->assertNull($account->external_id);
        $this->assertNotNull($account->promotions_synced_at);
        $this->assertDatabaseMissing('grocery_promotions', ['shop_variant_id' => 'OLD']);
        $this->assertDatabaseHas('grocery_promotions', ['id' => $kept->id, 'name' => 'Other account']);
        $this->assertDatabaseCount('grocery_promotions', 4);
        $this->assertDatabaseHas('grocery_promotions', [
            'grocery_account_id' => $account->id,
            'store_number' => '213217',
            'shop_variant_id' => '111',
            'source' => PromotionSource::Triweekly->value,
            'gross_cents' => 71399,
            'strike_gross_cents' => 74999,
            'brand' => null,
            'starts_on' => '2026-10-22',
            'ends_on' => '2026-11-11',
        ]);
        $this->assertDatabaseHas('grocery_promotions', [
            'grocery_account_id' => $account->id,
            'shop_variant_id' => '111',
            'source' => PromotionSource::Directed->value,
            'gross_cents' => 67800,
            'ends_on' => '2026-10-28',
        ]);
        $this->assertDatabaseCount('grocery_promotion_matches', 2);
        $this->assertSame(SyncRunStatus::Succeeded, $run->status);
        $this->assertSame('3 promotions, 2 match articles you buy', $run->summary);
        $this->assertSame(['promotions' => 3, 'matches' => 2], $run->details);
    }

    public function test_a_failed_fetch_keeps_the_previous_rows(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->withValidToken()->create(['user_id' => $user->id]);
        GroceryPromotion::factory()->for($account, 'account')->create([
            'shop_variant_id' => 'OLD',
            'name' => 'Still here',
        ]);

        $this->fakeMaxiClient([
            new Response(200, [], json_encode(['Status' => true])),
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['StoreId' => 213217]])),
            new Response(500, [], 'down'),
        ]);

        $run = $this->refresh($account);

        $this->assertDatabaseHas('grocery_promotions', ['shop_variant_id' => 'OLD', 'name' => 'Still here']);
        $this->assertNull($account->fresh()->promotions_synced_at);
        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertSame('Moj Maxi promotions could not be refreshed.', $run->message);
    }

    public function test_an_expired_session_clears_the_token_and_keeps_the_cache(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->withValidToken()->create(['user_id' => $user->id]);
        GroceryPromotion::factory()->for($account, 'account')->create(['shop_variant_id' => 'OLD']);

        $this->fakeMaxiClient([
            new Response(200, [], json_encode(['Status' => true])),
            new Response(401, [], 'nope'),
        ]);

        $run = $this->refresh($account);

        $account->refresh();
        $this->assertNull($account->access_token);
        $this->assertDatabaseHas('grocery_promotions', ['shop_variant_id' => 'OLD']);
        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertSame('The Moj Maxi session expired. Sign in again to refresh promotions.', $run->message);
    }

    public function test_a_profile_without_a_store_keeps_the_cache(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->withValidToken()->create(['user_id' => $user->id]);
        GroceryPromotion::factory()->for($account, 'account')->create(['shop_variant_id' => 'OLD']);

        $this->fakeMaxiClient([
            new Response(200, [], json_encode(['Status' => true])),
            new Response(200, [], json_encode(['Status' => true, 'Data' => null])),
        ]);

        $run = $this->refresh($account);

        $this->assertDatabaseHas('grocery_promotions', ['shop_variant_id' => 'OLD']);
        $this->assertNotNull($account->fresh()->access_token);
        $this->assertSame(MaxiClient::NO_STORE, $run->message);
    }

    public function test_an_account_that_cannot_authenticate_is_skipped(): void
    {
        $account = GroceryAccount::factory()->create([
            'access_token' => null,
            'refresh_token' => null,
            'password' => null,
        ]);

        $this->fakeMaxiClient([]);

        RefreshMaxiPromotions::dispatchUnattended();

        $this->assertDatabaseCount('sync_runs', 0);
        $this->assertNull($account->fresh()->promotions_synced_at);
    }

    public function test_an_overlapping_refresh_fails_the_report(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->withValidToken()->create(['user_id' => $user->id]);
        $this->fakeMaxiClient([]);
        $this->assertTrue(Cache::lock(RefreshMaxiPromotions::overlapKey($account->id), 60)->get());

        $run = SyncRun::open($user, SyncKind::PromotionRefresh, $account, 'Refreshing promotions');

        (new RefreshMaxiPromotions($account->id, $run->id))->handle(
            app(GroceryAccountAuthenticator::class),
            app(MaxiClient::class),
            app(PromotionMatcher::class),
        );

        $run->refresh();
        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertSame('A promotion refresh for this account is already running.', $run->message);
    }

    public function test_promotions_refresh_is_scheduled_at_four_utc(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'maxi-promotions');

        $this->assertNotNull($event);
        $this->assertSame('0 4 * * *', $event->expression);
    }

    private function refresh(GroceryAccount $account): SyncRun
    {
        $run = SyncRun::open($account->user, SyncKind::PromotionRefresh, $account, 'Refreshing promotions');

        (new RefreshMaxiPromotions($account->id, $run->id))->handle(
            app(GroceryAccountAuthenticator::class),
            app(MaxiClient::class),
            app(PromotionMatcher::class),
        );

        return $run->fresh();
    }

    private function fakeMaxiClient(array $responses): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses)), 'http_errors' => false]);
        $this->app->instance(MaxiClient::class, new MaxiClient($http));
    }

    private function purchase(GroceryAccount $account, string $name, $when, int $unitPriceCents): void
    {
        $receipt = GroceryReceipt::factory()->for($account, 'account')->create([
            'purchased_at' => $when,
        ]);
        GroceryReceiptItem::factory()->for($receipt, 'receipt')->create([
            'line_no' => 1,
            'name' => $name,
            'unit_price_cents' => $unitPriceCents,
            'total_cents' => $unitPriceCents,
        ]);
    }

    private function emptyFeed(): Response
    {
        return new Response(200, [], json_encode([
            'Status' => true,
            'Message' => 'Data not found...',
            'Data' => null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function article(string $id, string $name, float|int $discount, string $until): array
    {
        return [
            'ArticleId' => $id,
            'ArticleName' => $name,
            'ArticleBrand' => '',
            'RegularPrice' => 749.99,
            'DiscountPrice' => $discount,
            'DateFromForDiscountPrice' => '20261022',
            'DateToForDiscountPrice' => $until,
            'ArticleImageUrl' => 'https://example.test/'.$id.'.jpg',
        ];
    }
}
