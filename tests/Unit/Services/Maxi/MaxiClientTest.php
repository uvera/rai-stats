<?php

namespace Tests\Unit\Services\Maxi;

use App\Services\Maxi\MaxiAuthException;
use App\Services\Maxi\MaxiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MaxiClientTest extends TestCase
{
    private function client(array $responses): array
    {
        $mock = new MockHandler($responses);
        $http = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);

        return [new MaxiClient($http), $mock];
    }

    private function jwt(int $exp): string
    {
        return 'h.'.rtrim(strtr(base64_encode(json_encode(['email' => 'x@y.z', 'exp' => $exp])), '+/', '-_'), '=').'.s';
    }

    public function test_login_parses_token_expiry_from_jwt(): void
    {
        $exp = now()->addYear()->timestamp;
        [$client] = $this->client([
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['AccessToken' => $this->jwt($exp)]])),
        ]);

        $result = $client->login('a@b.c', 'secret', 'dev-uuid');

        $this->assertSame($exp, $result->expiresAt->timestamp);
        $this->assertStringStartsWith('h.', $result->accessToken);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        [$client] = $this->client([
            new Response(200, [], json_encode(['Status' => false, 'Message' => 'Wrong password'])),
        ]);

        $this->expectException(MaxiAuthException::class);
        $client->login('a@b.c', 'nope', 'dev-uuid');
    }

    public function test_register_device_accepts_a_200(): void
    {
        [$client] = $this->client([
            new Response(200, [], json_encode(['Status' => true, 'Data' => ['Id' => 42]])),
        ]);

        $client->registerDevice('token', 'dev-uuid');
        $this->addToAssertionCount(1);
    }

    public function test_register_device_raises_auth_exception_on_401(): void
    {
        [$client] = $this->client([new Response(401, [], 'nope')]);

        $this->expectException(MaxiAuthException::class);
        $client->registerDevice('stale', 'dev-uuid');
    }

    public function test_list_invoices_maps_rows(): void
    {
        [$client] = $this->client([
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
        $this->assertSame('abc123', $invoices[0]->invoiceHash);
        $this->assertSame(66194, $invoices[0]->totalCents);
        $this->assertSame('MAXI', $invoices[0]->storeFormat);
        $this->assertSame('2026-08-26 07:58', $invoices[0]->purchasedAt->format('Y-m-d H:i'));
    }

    public function test_list_invoices_raises_auth_exception_on_401(): void
    {
        [$client] = $this->client([new Response(401, [], 'nope')]);

        $this->expectException(MaxiAuthException::class);
        $client->listInvoices('stale-token', 'dev-uuid');
    }

    public function test_download_receipt_returns_bytes(): void
    {
        [$client] = $this->client([new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 fake')]);

        $this->assertSame('%PDF-1.4 fake', $client->downloadReceipt('https://example.test/eReceipt/x', 'dev-uuid'));
    }
}
