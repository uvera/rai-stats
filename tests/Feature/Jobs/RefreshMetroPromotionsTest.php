<?php

namespace Tests\Feature\Jobs;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Jobs\RaiffeisenImportJob;
use App\Jobs\RaiffeisenLoginJob;
use App\Jobs\RefreshMetroPromotions;
use App\Jobs\SyncGroceryAccountJob;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use App\Models\MetroPromotion;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Promotions\PromotionMatcher;
use App\Services\Receipts\GroceryAccountAuthenticator;
use App\Services\Receipts\Metro\MetroClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

class RefreshMetroPromotionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_promotions_and_matches_frequent_articles(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->metro()->withValidToken()->create([
            'user_id' => $user->id,
        ]);
        $other = GroceryAccount::factory()->metro()->create();

        MetroPromotion::factory()->for($account, 'account')->create([
            'shop_variant_id' => 'OLD',
            'name' => 'Gone',
        ]);
        $kept = MetroPromotion::factory()->for($other, 'account')->create([
            'shop_variant_id' => 'KEEP',
            'name' => 'Other account',
        ]);

        $this->purchase($account, 'COLLA PARMIGANO REGGIANO 200G', now()->subDays(20), 50000);
        $this->purchase($account, 'COLLA PARMIGANO REGGIANO 200G', now()->subDays(1), 48000);
        $this->purchase($account, 'MLEKO', now()->subDay(), 12000);
        $this->purchase($account, 'STARI SAPUN', now()->subDays(200), 9000);
        $this->purchase($account, 'STARI SAPUN', now()->subDays(10), 9500);

        $this->fakeMetroClient([
            new Response(200, [], json_encode(['cards' => [['cardholderId' => 'RS_9_100_1']]])),
            new Response(200, [], json_encode([
                'articleResults' => [$this->article('COLLA Tvrdi sir parmigano reggiano 200 g', 'BTY-1', 455.99, 599.99)],
                'nextPage' => 2,
            ])),
            new Response(200, [], json_encode([
                'articleResults' => [
                    $this->article('PALMOLIVE Gel za tuširanje naturals milk&honey 500 ml', 'BTY-2', 199.99, 299.99),
                    $this->article('STARI SAPUN 500 g', 'BTY-3', 100, 150),
                ],
            ])),
        ]);

        $run = $this->refresh($account);

        $this->assertSame('RS_9_100_1', $account->fresh()->external_id);
        $this->assertNotNull($account->fresh()->promotions_synced_at);
        $this->assertDatabaseMissing('metro_promotions', ['shop_variant_id' => 'OLD']);
        $this->assertDatabaseHas('metro_promotions', ['id' => $kept->id, 'name' => 'Other account']);
        $this->assertDatabaseCount('metro_promotions', 4);
        $this->assertDatabaseCount('metro_promotion_matches', 1);
        $this->assertDatabaseHas('metro_promotion_matches', [
            'article_name' => 'COLLA PARMIGANO REGGIANO 200G',
            'times_bought' => 2,
            'last_unit_price_cents' => 48000,
        ]);
        $this->assertDatabaseHas('metro_promotions', [
            'grocery_account_id' => $account->id,
            'store_number' => '9',
            'shop_variant_id' => 'BTY-1',
            'gross_cents' => 45599,
            'strike_gross_cents' => 59999,
            'brand' => 'COLLA',
        ]);
        $this->assertSame(SyncRunStatus::Succeeded, $run->status);
        $this->assertSame('3 promotions, 1 matches an article you buy', $run->summary);
        $this->assertSame(['promotions' => 3, 'matches' => 1], $run->details);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Promotion refresh finished', $notification->data['title']);
        $this->assertSame('success', $notification->data['status']);
        $this->assertSame('View report', $notification->data['actions'][0]['label']);
        $this->assertStringContainsString('/admin/sync-reports/'.$run->id, $notification->data['actions'][0]['url']);
    }

    public function test_a_failed_later_page_keeps_the_previous_rows(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->metro()->withValidToken()->create([
            'user_id' => $user->id,
        ]);
        MetroPromotion::factory()->for($account, 'account')->create([
            'shop_variant_id' => 'OLD',
            'name' => 'Still here',
        ]);

        $this->fakeMetroClient([
            new Response(200, [], json_encode(['cards' => [['cardholderId' => 'RS_9_100_1']]])),
            new Response(200, [], json_encode([
                'articleResults' => [$this->article('COLLA Tvrdi sir parmigano reggiano 200 g', 'BTY-1', 455.99, 599.99)],
                'nextPage' => 2,
            ])),
            new Response(504, [], 'gateway'),
            new Response(504, [], 'gateway'),
            new Response(504, [], 'gateway'),
        ]);

        $run = $this->refresh($account);

        $this->assertDatabaseHas('metro_promotions', ['shop_variant_id' => 'OLD', 'name' => 'Still here']);
        $this->assertDatabaseMissing('metro_promotions', ['shop_variant_id' => 'BTY-1']);
        $this->assertNull($account->fresh()->promotions_synced_at);
        $this->assertNull($account->fresh()->external_id);

        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Promotion refresh failed', $notification->data['title']);
        $this->assertSame('danger', $notification->data['status']);
        $this->assertSame('Metro promotions could not be refreshed.', $notification->data['body']);
        $this->assertStringContainsString('/admin/sync-reports/'.$run->id, $notification->data['actions'][0]['url']);
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_an_account_that_cannot_authenticate_is_skipped(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->metro()->create([
            'user_id' => $user->id,
            'access_token' => null,
            'refresh_token' => null,
            'password' => null,
        ]);

        $this->fakeMetroClient([]);

        RefreshMetroPromotions::dispatchUnattended();

        $this->assertDatabaseCount('sync_runs', 0);
        $this->assertDatabaseCount('metro_promotions', 0);
        $this->assertSame(0, $user->notifications()->count());
        $this->assertNull($account->fresh()->promotions_synced_at);
    }

    public function test_an_overlapping_refresh_fails_the_report(): void
    {
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->metro()->withValidToken()->create([
            'user_id' => $user->id,
        ]);
        $this->fakeMetroClient([new Response(500)]);
        $this->assertTrue(Cache::lock(RefreshMetroPromotions::overlapKey($account->id), 60)->get());

        $run = SyncRun::open($user, SyncKind::PromotionRefresh, $account, 'Refreshing promotions');

        (new RefreshMetroPromotions($account->id, $run->id))->handle(
            app(GroceryAccountAuthenticator::class),
            app(MetroClient::class),
            app(PromotionMatcher::class),
        );

        $run->refresh();
        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertSame('Refresh failed', $run->summary);
        $this->assertSame('A promotion refresh for this account is already running.', $run->message);
        $this->assertDatabaseCount('metro_promotions', 0);
    }

    public function test_the_queue_waits_longer_than_any_job_can_run(): void
    {
        $timeouts = array_map(
            fn (string $job) => (new ReflectionClass($job))->getDefaultProperties()['timeout'],
            [
                RefreshMetroPromotions::class,
                SyncGroceryAccountJob::class,
                RaiffeisenLoginJob::class,
                RaiffeisenImportJob::class,
            ],
        );

        $this->assertGreaterThan(max($timeouts), config('queue.connections.database.retry_after'));
    }

    public function test_promotions_refresh_is_scheduled_at_four_utc(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'metro-promotions');

        $this->assertNotNull($event);
        $this->assertSame('0 4 * * *', $event->expression);
    }

    private function refresh(GroceryAccount $account): SyncRun
    {
        $run = SyncRun::open($account->user, SyncKind::PromotionRefresh, $account, 'Refreshing promotions');

        (new RefreshMetroPromotions($account->id, $run->id))->handle(
            app(GroceryAccountAuthenticator::class),
            app(MetroClient::class),
            app(PromotionMatcher::class),
        );

        return $run->fresh();
    }

    private function fakeMetroClient(array $responses): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses)), 'http_errors' => false]);
        $this->app->instance(MetroClient::class, new MetroClient($http));
    }

    private function purchase(GroceryAccount $account, string $name, $when, int $unitPriceCents): void
    {
        $receipt = GroceryReceipt::factory()->metro()->for($account, 'account')->create([
            'purchased_at' => $when,
        ]);
        GroceryReceiptItem::factory()->for($receipt, 'receipt')->create([
            'line_no' => 1,
            'name' => $name,
            'unit_price_cents' => $unitPriceCents,
            'total_cents' => $unitPriceCents,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function article(string $name, string $shopVariantId, float $gross, float $strike): array
    {
        return [
            'name' => $name,
            'shopVariantId' => $shopVariantId,
            'imageUrl' => 'https://cdn.metro-group.com/rs/'.$shopVariantId.'.png',
            'bundles' => [[
                'brand' => strtok($name, ' '),
                'description' => $name,
                'fulfillment' => [[
                    'price' => [
                        'articleGross' => $gross,
                        'strikeThroughGross' => $strike,
                        'promotion' => [
                            'startDate' => '2026-10-21',
                            'endDate' => '2026-10-28',
                        ],
                    ],
                ]],
            ]],
        ];
    }
}
