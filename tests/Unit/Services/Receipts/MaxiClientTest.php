<?php

namespace Tests\Unit\Services\Receipts;

use App\Services\Receipts\Maxi\MaxiClient;
use App\Services\Receipts\ReceiptAuthException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class MaxiClientTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    private function client(array $responses): MaxiClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new MaxiClient(new Client(['handler' => $stack, 'http_errors' => false]));
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    private function jwt(int $exp): string
    {
        return 'h.'.rtrim(strtr(base64_encode(json_encode(['email' => 'x@y.z', 'exp' => $exp])), '+/', '-_'), '=').'.s';
    }

    public function test_login_parses_token_expiry_from_jwt(): void
    {
        $exp = now()->addMinutes(20)->timestamp;
        $client = $this->client([
            new Response(200, [], json_encode(['Status' => true, 'Data' => [
                'AccessToken' => $this->jwt($exp),
                'RefreshToken' => 'opaque-refresh',
            ]])),
        ]);

        $result = $client->login('a@b.c', 'secret', 'dev-uuid');

        $this->assertSame($exp, $result->expiresAt->timestamp);
        $this->assertStringStartsWith('h.', $result->accessToken);
        $this->assertSame('opaque-refresh', $result->refreshToken);
        $this->assertSame(
            'https://mobileloyalty-prod.delhaize.rs/prod/A/api/AppEndUser/GetAppUserByEmailAndPassword',
            (string) $this->lastRequest()->getUri()
        );
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['Status' => false, 'Message' => 'Wrong password'])),
        ]);

        $this->expectException(ReceiptAuthException::class);
        $client->login('a@b.c', 'nope', 'dev-uuid');
    }

    public function test_login_without_a_token_is_an_auth_failure(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'Status' => true,
                'Message' => 'Data not found...',
                'Data' => null,
            ])),
        ]);

        $this->expectException(ReceiptAuthException::class);
        $this->expectExceptionMessage('Data not found...');
        $client->login('a@b.c', 'nope', 'dev-uuid');
    }

    public function test_register_device_accepts_a_200(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['Id' => 42]])),
        ]);

        $client->registerDevice('token', 'dev-uuid');

        $request = $this->lastRequest();
        $this->assertSame(
            'https://mobileloyalty-prod.delhaize.rs/prod/A/api/AppEndUser/InsertAppUserLoginTracker',
            (string) $request->getUri()
        );
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('2.6.1', $body['Version']);
        $this->assertSame('342', $body['BuildNumber']);
    }

    public function test_register_device_raises_auth_exception_on_401(): void
    {
        $client = $this->client([new Response(401, [], 'nope')]);

        $this->expectException(ReceiptAuthException::class);
        $client->registerDevice('stale', 'dev-uuid');
    }

    public function test_list_invoices_maps_rows(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['Status' => true, 'Data' => [
                [
                    'InvoiceNumberHash' => 'abc123',
                    'FormattedInvoiceDate' => '20260826',
                    'FormattedInvoiceTime' => '758',
                    'TotalAmount' => 661.94,
                    'StoreName' => 'Maxi 249',
                    'StoreAddress' => 'Temerinski put 28',
                    'StoreFromat' => 'MAXI',
                    'InvoicePdfUrl' => 'https://example.test/eReceipt/abc123',
                ],
            ]])),
        ]);

        $invoices = $client->listInvoices('token', 'dev-uuid');

        $this->assertCount(1, $invoices);
        $this->assertSame('abc123', $invoices[0]->externalRef);
        $this->assertSame(66194, $invoices[0]->totalCents);
        $this->assertSame('MAXI', $invoices[0]->storeFormat);
        $this->assertSame('2026-08-26 07:58', $invoices[0]->purchasedAt->format('Y-m-d H:i'));
    }

    public function test_list_invoices_raises_auth_exception_on_401(): void
    {
        $client = $this->client([new Response(401, [], 'nope')]);

        $this->expectException(ReceiptAuthException::class);
        $client->listInvoices('stale-token', 'dev-uuid');
    }

    public function test_download_receipt_returns_bytes(): void
    {
        $client = $this->client([new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 fake')]);

        $this->assertSame('%PDF-1.4 fake', $client->downloadReceipt('https://example.test/eReceipt/x', 'dev-uuid'));
    }
}
