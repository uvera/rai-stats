<?php

namespace Tests\Unit\Services\Receipts;

use App\Services\Receipts\Metro\MetroClient;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class MetroClientTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    private function client(array $responses): MetroClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new MetroClient(new Client(['handler' => $stack, 'http_errors' => false]));
    }

    private function jwt(int $exp): string
    {
        return 'h.'.rtrim(strtr(base64_encode(json_encode(['email' => 'x@y.z', 'exp' => $exp])), '+/', '-_'), '=').'.s';
    }

    public function test_login_returns_token_with_refresh_token(): void
    {
        $exp = time() + 3600;
        $client = $this->client([
            new Response(200, [], json_encode([
                'access_token' => $this->jwt($exp),
                'refresh_token' => 'rt-123',
                'expires_in' => 3599,
            ])),
        ]);

        $token = $client->login('a@b.c', 'secret');

        $this->assertSame($exp, $token->expiresAt->timestamp);
        $this->assertSame('rt-123', $token->refreshToken);
        $request = $this->history[0]['request'];
        $correlationId = $request->getHeaderLine('X-Correlation-Id');
        $this->assertNotSame('', $correlationId);
        $this->assertSame($correlationId, $request->getHeaderLine('Calltreeid'));
    }

    public function test_rejected_credentials_raise_auth_exception(): void
    {
        $client = $this->client([
            new Response(400, [], json_encode(['error' => 'invalid_grant', 'error_description' => 'bad'])),
        ]);

        $this->expectException(ReceiptAuthException::class);
        $client->login('a@b.c', 'nope');
    }

    public function test_html_block_is_reported_by_status(): void
    {
        $client = $this->client([
            new Response(403, [], '<HTML><HEAD><TITLE>Access Denied</TITLE></HEAD><BODY>'),
        ]);

        $this->expectException(ReceiptException::class);
        $this->expectExceptionMessage('Metro token request was blocked (403).');
        $client->login('a@b.c', 'secret');
    }

    public function test_list_invoices_paginates(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['numFound' => 3, 'invoices' => [['transactionId' => 'a'], ['transactionId' => 'b']]])),
            new Response(200, [], json_encode(['numFound' => 3, 'invoices' => [['transactionId' => 'c']]])),
        ]);

        $rows = $client->listInvoices('token');

        $this->assertCount(3, $rows);
        $this->assertSame('c', $rows[2]['transactionId']);
    }

    public function test_fetch_articles_unwraps_the_list(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['articles' => [['description' => 'METRO LIMETA']]])),
        ]);

        $this->assertSame('METRO LIMETA', $client->fetchArticles('token', 'SRB_1')[0]['description']);
    }

    public function test_401_raises_auth_exception(): void
    {
        $client = $this->client([new Response(401, [], 'nope')]);

        $this->expectException(ReceiptAuthException::class);
        $client->listInvoices('stale');
    }
}
