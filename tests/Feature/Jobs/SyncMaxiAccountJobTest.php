<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncMaxiAccountJob;
use App\Models\MaxiAccount;
use App\Services\Maxi\MaxiClient;
use App\Services\Maxi\ReceiptImporter;
use App\Support\MaxiSyncSession;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMaxiAccountJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMaxiClient(array $responses): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $this->app->instance(MaxiClient::class, new MaxiClient($http));
    }

    private function jwt(): string
    {
        return 'h.'.rtrim(strtr(base64_encode(json_encode(['exp' => now()->addYear()->timestamp])), '+/', '-_'), '=').'.s';
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

    public function test_logs_in_with_password_and_imports_receipts(): void
    {
        $account = MaxiAccount::factory()->create();

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

        $sessionId = MaxiSyncSession::start($account->id);
        MaxiSyncSession::setPassword($sessionId, 'secret');

        (new SyncMaxiAccountJob($account->id, $sessionId))->handle(
            app(MaxiClient::class),
            app(ReceiptImporter::class),
        );

        $this->assertDatabaseCount('maxi_receipts', 2);
        $this->assertNotNull($account->fresh()->access_token);
        $this->assertNotNull($account->fresh()->last_synced_at);
        $this->assertSame('done', MaxiSyncSession::getState($sessionId)['status']);
    }

    public function test_second_sync_only_imports_new_invoices(): void
    {
        $account = MaxiAccount::factory()->withValidToken()->create();

        $this->fakeMaxiClient([
            $this->trackerResponse(),
            new Response(200, [], json_encode(['Status' => true, 'Data' => [$this->invoiceRow('inv-a')]])),
            new Response(200, [], $this->pdf()),
            // second run: register again, same invoice list, no PDF fetch expected
            $this->trackerResponse(),
            new Response(200, [], json_encode(['Status' => true, 'Data' => [$this->invoiceRow('inv-a')]])),
        ]);

        $s1 = MaxiSyncSession::start($account->id);
        (new SyncMaxiAccountJob($account->id, $s1))->handle(app(MaxiClient::class), app(ReceiptImporter::class));

        $s2 = MaxiSyncSession::start($account->id);
        (new SyncMaxiAccountJob($account->id, $s2))->handle(app(MaxiClient::class), app(ReceiptImporter::class));

        $this->assertDatabaseCount('maxi_receipts', 1);
        $this->assertSame(0, MaxiSyncSession::getState($s2)['imported']);
    }

    public function test_expired_token_without_password_asks_for_one(): void
    {
        $account = MaxiAccount::factory()->create(['access_token' => null]);
        $this->fakeMaxiClient([]);

        $sessionId = MaxiSyncSession::start($account->id);

        (new SyncMaxiAccountJob($account->id, $sessionId))->handle(
            app(MaxiClient::class),
            app(ReceiptImporter::class),
        );

        $this->assertSame('needs_password', MaxiSyncSession::getState($sessionId)['status']);
        $this->assertDatabaseCount('maxi_receipts', 0);
    }
}
