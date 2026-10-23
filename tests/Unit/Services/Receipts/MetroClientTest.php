<?php

namespace Tests\Unit\Services\Receipts;

use App\Services\Receipts\Metro\MetroClient;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
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

    public function test_ranked_promotions_follow_next_page(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode($this->promoPage('Soap', 2))),
            new Response(200, [], json_encode($this->promoPage('Rum', null))),
        ]);

        $rows = $client->listRankedPromotions('token', 'rs', '22', 'RS_22_1');

        $this->assertSame(['Soap', 'Rum'], array_column($rows, 'name'));

        $first = $this->history[0]['request'];
        $this->assertSame(
            '/tailored-assortment/v2/rs/ranked-promotions/store/22',
            $first->getUri()->getPath()
        );
        parse_str($first->getUri()->getQuery(), $query);
        $this->assertSame('en-US', $query['locale']);
        $this->assertSame('RS_22_1', $query['cardholderId']);
        $this->assertSame('1', $query['pageNumber']);
        $this->assertSame('store', $query['scope']);
        $this->assertSame('Bearer token', $first->getHeaderLine('Authorization'));

        parse_str($this->history[1]['request']->getUri()->getQuery(), $second);
        $this->assertSame('2', $second['pageNumber']);
    }

    public function test_ranked_promotions_stop_on_an_empty_page(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode($this->promoPage('Soap', 2))),
            new Response(200, [], json_encode(['articleResults' => [], 'nextPage' => 3])),
            new Response(200, [], json_encode($this->promoPage('Should not be fetched', 4))),
        ]);

        $rows = $client->listRankedPromotions('token', 'RS', '22', 'RS_22_1');

        $this->assertSame(['Soap'], array_column($rows, 'name'));
        $this->assertCount(2, $this->history);
        $this->assertSame(
            '/tailored-assortment/v2/rs/ranked-promotions/store/22',
            $this->history[0]['request']->getUri()->getPath()
        );
    }

    public function test_ranked_promotions_401_raises_auth_exception(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode($this->promoPage('Soap', 2))),
            new Response(401, [], 'nope'),
        ]);

        $this->expectException(ReceiptAuthException::class);
        $client->listRankedPromotions('token', 'rs', '22', 'RS_22_1');
    }

    public function test_ranked_promotions_5xx_retries_then_throws_without_a_partial_list(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode($this->promoPage('Soap', 2))),
            new Response(504, [], 'gateway'),
            new Response(504, [], 'gateway'),
            new Response(504, [], 'gateway'),
        ]);

        try {
            $client->listRankedPromotions('token', 'rs', '22', 'RS_22_1');
            $this->fail('A failed page should not return a partial list.');
        } catch (ReceiptException $e) {
            $this->assertStringNotContainsString('Soap', $e->getMessage());
        }

        $this->assertCount(4, $this->history);
    }

    public function test_ranked_promotions_retry_a_timeout(): void
    {
        $client = $this->client([
            new ConnectException('cURL error 28', new Request('GET', 'https://api.metronom.dev/promo')),
            new Response(200, [], json_encode($this->promoPage('Soap', null))),
        ]);

        $rows = $client->listRankedPromotions('token', 'rs', '22', 'RS_22_1');

        $this->assertSame(['Soap'], array_column($rows, 'name'));
        $this->assertCount(2, $this->history);
    }

    public function test_ranked_promotions_give_up_after_three_timeouts(): void
    {
        $timeout = new ConnectException('cURL error 28', new Request('GET', 'https://api.metronom.dev/promo'));
        $client = $this->client([$timeout, $timeout, $timeout]);

        try {
            $client->listRankedPromotions('token', 'rs', '22', 'RS_22_1');
            $this->fail('A timed out page should not return a partial list.');
        } catch (ReceiptException $e) {
            $this->assertSame('Metro promotions request timed out.', $e->getMessage());
        }

        $this->assertCount(3, $this->history);
    }

    public function test_ranked_promotions_stop_after_forty_pages(): void
    {
        $responses = [];
        for ($page = 1; $page <= 41; $page++) {
            $responses[] = new Response(200, [], json_encode($this->promoPage('Item '.$page, $page + 1)));
        }

        $client = $this->client($responses);
        $rows = $client->listRankedPromotions('token', 'rs', '22', 'RS_22_1');

        $this->assertCount(40, $rows);
        $this->assertCount(40, $this->history);
    }

    /**
     * @return array<string, mixed>
     */
    private function promoPage(string $name, ?int $nextPage): array
    {
        $page = [
            'articleResults' => [['name' => $name]],
            'personalized' => true,
            'numberOfArticles' => 1,
        ];

        if ($nextPage !== null) {
            $page['nextPage'] = $nextPage;
        }

        return $page;
    }
}
