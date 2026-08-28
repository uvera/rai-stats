<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncGroceryAccountJob;
use App\Models\GroceryAccount;
use App\Services\Receipts\Maxi\MaxiClient;
use App\Services\Receipts\Metro\MetroClient;
use App\Services\Receipts\ReceiptImporter;
use App\Support\GrocerySyncSession;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncGroceryAccountJobTest extends TestCase
{
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

    private function runSync(GroceryAccount $account, string $sessionId): void
    {
        (new SyncGroceryAccountJob($account->id, $sessionId))->handle(app(ReceiptImporter::class));
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
        $this->runSync($account, $sessionId);

        $this->assertSame('needs_password', GrocerySyncSession::getState($sessionId)['status']);
        $this->assertDatabaseCount('grocery_receipts', 0);
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
}
