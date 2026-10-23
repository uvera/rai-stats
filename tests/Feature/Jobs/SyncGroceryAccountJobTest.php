<?php

namespace Tests\Feature\Jobs;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Jobs\SyncGroceryAccountJob;
use App\Models\GroceryAccount;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\GroceryAccountAuthenticator;
use App\Services\Receipts\Maxi\MaxiClient;
use App\Services\Receipts\Maxi\MaxiProvider;
use App\Services\Receipts\Metro\MetroClient;
use App\Services\Receipts\ReceiptException;
use App\Services\Receipts\ReceiptImporter;
use App\Support\GrocerySyncSession;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class SyncGroceryAccountJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private function fakeMaxiClient(array $responses): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $this->app->instance(MaxiClient::class, new MaxiClient($http));
    }

    private function fakeMetroClient(array $responses): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses)), 'http_errors' => false]);
        $this->app->instance(MetroClient::class, new MetroClient($http));
    }

    private function runSync(GroceryAccount $account, string $sessionId): SyncRun
    {
        $run = SyncRun::open(User::factory()->admin()->create(), SyncKind::GrocerySync, $account, 'Syncing receipts');
        (new SyncGroceryAccountJob($account->id, $sessionId, $run->id))->handle(
            app(ReceiptImporter::class),
            app(GroceryAccountAuthenticator::class),
        );

        return $run->fresh();
    }

    private function jwt(): string
    {
        return 'h.'.rtrim(strtr(base64_encode(json_encode(['exp' => now()->addHour()->timestamp])), '+/', '-_'), '=').'.s';
    }

    private function pdf(): string
    {
        return file_get_contents(base_path('tests/Fixtures/maxi/ereceipt-sample.pdf'));
    }

    private function trackerResponse(): Response
    {
        return new Response(200, [], json_encode(['Status' => true, 'Data' => ['Id' => 1]]));
    }

    private function invoiceRow(string $hash): array
    {
        return [
            'InvoiceNumberHash' => $hash,
            'FormattedInvoiceDate' => '20260826',
            'FormattedInvoiceTime' => '1933',
            'TotalAmount' => 3125.45,
            'StoreName' => 'Mega Maxi 02 Novi Sad',
            'StoreAddress' => 'Tekelijina bb',
            'StoreFromat' => 'MEGA MAXI',
            'InvoicePdfUrl' => 'https://example.test/eReceipt/'.$hash,
        ];
    }

    // --- Moj Maxi -----------------------------------------------------------

    public function test_logs_in_with_session_password_and_imports_receipts(): void
    {
        $account = GroceryAccount::factory()->create();

        $this->fakeMaxiClient([
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['AccessToken' => $this->jwt()]])),
            $this->trackerResponse(),
            new Response(200, [], json_encode(['Status' => true, 'Data' => [
                $this->invoiceRow('inv-a'),
                $this->invoiceRow('inv-b'),
            ]])),
            new Response(200, [], $this->pdf()),
            new Response(200, [], $this->pdf()),
        ]);

        $sessionId = GrocerySyncSession::start($account->id);
        GrocerySyncSession::setPassword($sessionId, 'secret');

        $this->runSync($account, $sessionId);

        $this->assertDatabaseCount('grocery_receipts', 2);
        $this->assertNotNull($account->fresh()->access_token);
        $this->assertNotNull($account->fresh()->last_synced_at);
        $this->assertSame('done', GrocerySyncSession::getState($sessionId)['status']);

        $run = SyncRun::query()->first();
        $this->assertSame(SyncRunStatus::Succeeded, $run->status);
        $this->assertSame('Imported 2 receipts', $run->summary);
        $this->assertSame(2, $run->details['imported']);
        $this->assertNotificationLinksTo($run);
    }

    public function test_uses_stored_password_without_a_session_password(): void
    {
        $account = GroceryAccount::factory()->withPassword()->create();

        $this->fakeMaxiClient([
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['AccessToken' => $this->jwt()]])),
            $this->trackerResponse(),
            new Response(200, [], json_encode(['Status' => true, 'Data' => [$this->invoiceRow('inv-a')]])),
            new Response(200, [], $this->pdf()),
        ]);

        $sessionId = GrocerySyncSession::start($account->id);
        $this->runSync($account, $sessionId);

        $this->assertDatabaseCount('grocery_receipts', 1);
        $this->assertSame('done', GrocerySyncSession::getState($sessionId)['status']);
    }

    public function test_second_sync_only_imports_new_invoices(): void
    {
        $account = GroceryAccount::factory()->withValidToken()->create();

        $this->fakeMaxiClient([
            $this->trackerResponse(),
            new Response(200, [], json_encode(['Status' => true, 'Data' => [$this->invoiceRow('inv-a')]])),
            new Response(200, [], $this->pdf()),
            $this->trackerResponse(),
            new Response(200, [], json_encode(['Status' => true, 'Data' => [$this->invoiceRow('inv-a')]])),
        ]);

        $s1 = GrocerySyncSession::start($account->id);
        $this->runSync($account, $s1);

        $s2 = GrocerySyncSession::start($account->id);
        $this->runSync($account, $s2);

        $this->assertDatabaseCount('grocery_receipts', 1);
        $this->assertSame(0, GrocerySyncSession::getState($s2)['imported']);
    }

    public function test_expired_token_without_password_asks_for_one(): void
    {
        $account = GroceryAccount::factory()->create(['access_token' => null]);
        $this->fakeMaxiClient([]);

        $sessionId = GrocerySyncSession::start($account->id);
        $run = $this->runSync($account, $sessionId);

        $this->assertSame('needs_password', GrocerySyncSession::getState($sessionId)['status']);
        $this->assertSame(SyncRunStatus::NeedsPassword, $run->status);
        $this->assertStringContainsString('password', $run->message);
        $this->assertDatabaseCount('grocery_receipts', 0);
        $this->assertSame('warning', $run->user->notifications()->first()->data['status']);
    }

    // --- Metro ------------------------------------------------------------

    private function metroToken(): string
    {
        return json_encode([
            'access_token' => $this->jwt(),
            'refresh_token' => 'rt-'.uniqid(),
            'expires_in' => 3599,
            'token_type' => 'Bearer',
        ]);
    }

    private function metroInvoices(): string
    {
        return json_encode([
            'numFound' => 1,
            'invoices' => [[
                'invoiceDate' => '2026-08-23T12:34:33.000Z',
                'invoiceNumber' => '1/0(022)0011/235094',
                'transactionId' => 'SRB_22_11_1089199_20260823123433',
                'currency' => 'RSD',
                'totalAmount' => 354.65,
                'netAmount' => 268.77,
                'cardholderId' => 'RS_22_760829_1',
            ]],
        ]);
    }

    private function metroArticles(): string
    {
        return json_encode([
            'articles' => [
                ['description' => 'METRO LIMETA', 'quantity' => 0.405, 'netItemPrice' => 663.62, 'grossItemPrice' => 729.98, 'netItemTotal' => 268.77, 'grossItemTotal' => 354.65],
            ],
        ]);
    }

    public function test_metro_refreshes_the_token_and_imports(): void
    {
        $account = GroceryAccount::factory()->metro()->create([
            'refresh_token' => 'stored-refresh-token',
            'access_token' => null,
        ]);

        $this->fakeMetroClient([
            new Response(200, [], $this->metroToken()),      // refresh grant
            new Response(200, [], $this->metroInvoices()),   // invoice list
            new Response(200, [], $this->metroArticles()),   // articles
            new Response(200, [], '%PDF-1.4 fake'),          // pdf
        ]);

        $sessionId = GrocerySyncSession::start($account->id);
        $this->runSync($account, $sessionId);

        $this->assertSame('done', GrocerySyncSession::getState($sessionId)['status']);
        $this->assertDatabaseHas('grocery_receipts', [
            'provider' => 'metro',
            'external_ref' => 'SRB_22_11_1089199_20260823123433',
            'total_cents' => 35465,
            'net_total_cents' => 26877,
        ]);
        $this->assertDatabaseHas('grocery_receipt_items', ['name' => 'METRO LIMETA', 'total_cents' => 35465]);
        $this->assertNotNull($account->fresh()->access_token);
    }

    public function test_metro_falls_back_to_stored_password_when_refresh_fails(): void
    {
        $account = GroceryAccount::factory()->metro()->withPassword()->create([
            'refresh_token' => 'expired-refresh-token',
            'access_token' => null,
        ]);

        $this->fakeMetroClient([
            new Response(400, [], json_encode(['error' => 'invalid_grant'])), // refresh rejected
            new Response(200, [], $this->metroToken()),                        // password login
            new Response(200, [], $this->metroInvoices()),
            new Response(200, [], $this->metroArticles()),
            new Response(200, [], '%PDF-1.4 fake'),
        ]);

        $sessionId = GrocerySyncSession::start($account->id);
        $this->runSync($account, $sessionId);

        $this->assertSame('done', GrocerySyncSession::getState($sessionId)['status']);
        $this->assertDatabaseCount('grocery_receipts', 1);
    }

    public function test_secrets_are_stored_encrypted(): void
    {
        $account = GroceryAccount::factory()->metro()->create([
            'password' => 's3cr3t-pw',
            'refresh_token' => 'rt-secret',
        ]);

        $raw = \DB::table('grocery_accounts')->where('id', $account->id)->first();

        $this->assertNotSame('s3cr3t-pw', $raw->password);
        $this->assertNotSame('rt-secret', $raw->refresh_token);
        $this->assertSame('s3cr3t-pw', $account->fresh()->password);
    }

    public function test_a_provider_error_is_stored_on_the_report(): void
    {
        $account = GroceryAccount::factory()->withValidToken()->create();
        $client = Mockery::mock(ProviderClient::class);
        $client->shouldReceive('listInvoices')->once()->andThrow(new ReceiptException('Unexpected GetInvoicesByUser status 500.'));
        $this->app->instance(MaxiProvider::class, $client);

        $sessionId = GrocerySyncSession::start($account->id);
        $run = $this->runSync($account, $sessionId);

        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertSame('Unexpected GetInvoicesByUser status 500.', $run->message);
        $this->assertSame('danger', $run->user->notifications()->first()->data['status']);
        $this->assertSame('failed', GrocerySyncSession::getState($sessionId)['status']);
    }

    public function test_an_unexpected_error_does_not_leak_into_the_report(): void
    {
        $account = GroceryAccount::factory()->withValidToken()->create();
        $client = Mockery::mock(ProviderClient::class);
        $client->shouldReceive('listInvoices')->once()->andThrow(new \RuntimeException('upstream body token=secret-value'));
        $this->app->instance(MaxiProvider::class, $client);

        $sessionId = GrocerySyncSession::start($account->id);
        $run = $this->runSync($account, $sessionId);

        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertStringNotContainsString('secret-value', (string) $run->message);
        $this->assertStringContainsString('unexpectedly', (string) $run->message);
    }

    public function test_the_failed_hook_marks_the_report_failed(): void
    {
        $account = GroceryAccount::factory()->create();
        $run = SyncRun::open(User::factory()->admin()->create(), SyncKind::GrocerySync, $account, 'Syncing receipts');
        $sessionId = GrocerySyncSession::start($account->id);

        (new SyncGroceryAccountJob($account->id, $sessionId, $run->id))->failed(new \RuntimeException('worker died'));

        $run->refresh();
        $this->assertSame(SyncRunStatus::Failed, $run->status);
        $this->assertStringContainsString('unexpectedly', (string) $run->message);
        $this->assertSame('failed', GrocerySyncSession::getState($sessionId)['status']);
    }

    private function assertNotificationLinksTo(SyncRun $run): void
    {
        $notification = $run->user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('success', $notification->data['status']);
        $this->assertSame('View report', $notification->data['actions'][0]['label']);
        $this->assertStringContainsString('/admin/sync-reports/'.$run->id, $notification->data['actions'][0]['url']);
    }
}
