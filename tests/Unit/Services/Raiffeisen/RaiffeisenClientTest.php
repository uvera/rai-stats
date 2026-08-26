<?php

namespace Tests\Unit\Services\Raiffeisen;

use App\Services\Raiffeisen\Data\TransactionType;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Services\Raiffeisen\RaiffeisenException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class RaiffeisenClientTest extends TestCase
{
    private function clientWithMockedResponses(array $responses): array
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $http = new Client(['handler' => $handlerStack]);

        return [new RaiffeisenClient(http: $http), $mock];
    }

    /**
     * The SSE /connect call bypasses Guzzle entirely (see RawSseStream) - so
     * the push-flow tests mock it separately via the injectable stream
     * factory instead of a Guzzle response.
     */
    private function clientForPushFlow(array $guzzleResponses, string $sseBody): RaiffeisenClient
    {
        $mock = new MockHandler($guzzleResponses);
        $http = new Client(['handler' => HandlerStack::create($mock)]);
        $fakeStream = new FakeSseStream($sseBody);

        return new RaiffeisenClient(http: $http, sseStreamFactory: fn () => $fakeStream);
    }

    public function test_login_font_without_2fa(): void
    {
        [$client] = $this->clientWithMockedResponses([
            new Response(200, [], json_encode([
                'Ticket' => 'ticket-123',
                'RequestToken' => 'req-token',
                'ForceSecondLogin' => false,
                'SecurityUserID' => 42,
                'GeneratedSessionID' => 1,
                'FailedAttempts' => 0,
                'TempBlockPeriodInMinutes' => 0,
            ])),
        ]);

        $result = $client->loginFont('testuser', 'testpass123');

        $this->assertSame('ticket-123', $result->ticket);
        $this->assertFalse($result->forceSecondLogin);
        $this->assertSame(1, $result->generatedSessionId);
    }

    public function test_login_font_strips_bom(): void
    {
        [$client] = $this->clientWithMockedResponses([
            new Response(200, [], "\xEF\xBB\xBF".json_encode([
                'Ticket' => 't',
                'RequestToken' => 'r',
                'ForceSecondLogin' => true,
                'SecurityUserID' => 1,
                'GeneratedSessionID' => 2,
                'FailedAttempts' => 0,
                'TempBlockPeriodInMinutes' => 0,
            ])),
        ]);

        $result = $client->loginFont('testuser', 'testpass123');

        $this->assertTrue($result->forceSecondLogin);
    }

    public function test_all_account_balance_parses_rows_and_tolerates_empty_last_transaction(): void
    {
        // Field indices per the bank's real shape: [1]=number [2]=description
        // [3]=currency [4]=total [5]=available [6]=lastTxAmount (often empty)
        // [7]=lastTxDate (often empty) [13]=productCoreID [14]=currencyNumeric.
        $row = array_fill(0, 19, '');
        $row[1] = '265000000677229272';
        $row[2] = 'Transakcioni depoziti stanovništva';
        $row[3] = 'RSD';
        $row[4] = '63664.36';
        $row[5] = '22310.49';
        $row[6] = '';
        $row[7] = '';
        $row[13] = '33';
        $row[14] = '941';

        [$client] = $this->clientWithMockedResponses([
            new Response(200, [], json_encode([$row])),
        ]);

        $accounts = $client->allAccountBalance();

        $this->assertCount(1, $accounts);
        $account = $accounts[0];
        $this->assertSame('265000000677229272', $account->number);
        $this->assertSame('RSD', $account->currencyCode);
        $this->assertSame('941', $account->currencyCodeNumeric);
        $this->assertSame('33', $account->productCoreId);
        $this->assertSame(6366436, $account->totalAmountCents);
        $this->assertSame(2231049, $account->availableAmountCents);
        $this->assertNull($account->lastTransactionAmountCents);
        $this->assertNull($account->lastTransactionDate);
    }

    public function test_turnover_sign_convention_for_a_spend_and_an_income_row(): void
    {
        // A spend (ATM withdrawal): nonzero in the credit slot [8] must be
        // negated. An income (incoming transfer): nonzero in the debit slot
        // [9] is used as-is, positive. Verified against real bank data.
        $spendRow = array_fill(0, 15, '');
        $spendRow[1] = '941';
        $spendRow[2] = 'RSD';
        $spendRow[3] = '24.08.2026 00:00:00';
        $spendRow[6] = 'bankomat';
        $spendRow[7] = '488718833853';
        $spendRow[8] = '20000';
        $spendRow[9] = '0';
        $spendRow[11] = 'ATM withdrawal';
        $spendRow[12] = '17263978239181#3#81#488718833853';
        $spendRow[13] = 'Other';

        $incomeRow = array_fill(0, 15, '');
        $incomeRow[1] = '941';
        $incomeRow[2] = 'RSD';
        $incomeRow[3] = '25.08.2026 22:46:34';
        $incomeRow[6] = 'Some Payer';
        $incomeRow[7] = '';
        $incomeRow[8] = '0';
        $incomeRow[9] = '139750.00';
        $incomeRow[11] = 'Incoming transfer';
        $incomeRow[12] = '1613262379394966#2#9#5613262379394925';
        $incomeRow[13] = 'Income';

        [$client] = $this->clientWithMockedResponses([
            new Response(200, [], json_encode([[null, [$spendRow, $incomeRow]]])),
        ]);

        $transactions = $client->transactionalAccountTurnover('33', '265000000677229272', '941', '01.01.2026', '26.08.2026');

        $this->assertCount(2, $transactions);

        $spend = $transactions[0];
        $this->assertSame(-2000000, $spend->amountCents);
        $this->assertSame('bankomat', $spend->place);
        $this->assertSame(TransactionType::Other, $spend->type);

        $income = $transactions[1];
        $this->assertSame(13975000, $income->amountCents);
        $this->assertSame(TransactionType::Income, $income->type);
    }

    public function test_turnover_returns_empty_array_when_response_is_empty(): void
    {
        [$client] = $this->clientWithMockedResponses([
            new Response(200, [], json_encode([])),
        ]);

        $transactions = $client->transactionalAccountTurnover('33', '265000000677229272', '941', '01.01.2026', '26.08.2026');

        $this->assertSame([], $transactions);
    }

    public function test_reserved_funds_are_always_negated(): void
    {
        $row = array_fill(0, 6, '');
        $row[1] = '24.08.2026 12:00:00';
        $row[2] = 'Pending POS hold';
        $row[3] = '500.00';
        $row[4] = 'RSD';
        $row[5] = '941';

        [$client] = $this->clientWithMockedResponses([
            new Response(200, [], json_encode([$row])),
        ]);

        $reserved = $client->transactionalAccountReservedFunds('265000000677229272');

        $this->assertCount(1, $reserved);
        $this->assertSame(-50000, $reserved[0]->amountCents);
    }

    public function test_full_2fa_push_approval_flow(): void
    {
        $sseBody = implode('', [
            "data: initialized\n",
            'data: '.json_encode([
                'M' => [[
                    'H' => 'ibankinghub',
                    'M' => 'LoginUPRequestApproved',
                    'A' => [[
                        'Status' => 'APPROVED',
                        'RequestId' => 'req-1',
                        'FirstStepTicket' => 'ticket-123',
                        'PushRequestContent' => 'push-content',
                    ]],
                ]],
            ])."\n",
        ]);

        $client = $this->clientForPushFlow([
            new Response(200, [], json_encode(['ConnectionToken' => 'conn-token', 'ConnectionId' => 'id', 'ProtocolVersion' => '2.1'])),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ], $sseBody);

        $result = $client->requestLoginPush('ticket-123', 'testuser', timeoutSeconds: 5);

        $this->assertTrue($result->isApproved());
        $this->assertSame('ticket-123', $result->firstStepTicket);
        $this->assertSame('push-content', $result->pushRequestContent);
    }

    public function test_push_flow_throws_on_rejected_status(): void
    {
        $sseBody = implode('', [
            "data: initialized\n",
            'data: '.json_encode([
                'M' => [[
                    'H' => 'ibankinghub',
                    'M' => 'LoginUPRequestApproved',
                    'A' => [[
                        'Status' => 'REJECTED',
                        'RequestId' => 'req-1',
                        'FirstStepTicket' => 'ticket-123',
                        'PushRequestContent' => 'push-content',
                    ]],
                ]],
            ])."\n",
        ]);

        $client = $this->clientForPushFlow([
            new Response(200, [], json_encode(['ConnectionToken' => 'conn-token'])),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ], $sseBody);

        $this->expectException(RaiffeisenException::class);
        $this->expectExceptionMessage('Login push REJECTED');

        $client->requestLoginPush('ticket-123', 'testuser', timeoutSeconds: 5);
    }
}

/**
 * Duck-types RawSseStream's public surface (read/eof/statusCode/
 * responseHeaders/setCookieHeaders) without opening a real socket.
 */
class FakeSseStream
{
    public int $statusCode = 200;

    public array $responseHeaders = [];

    public array $setCookieHeaders = [];

    private string $buffer;

    public function __construct(string $body)
    {
        $this->buffer = $body;
    }

    public function read(int $length): string
    {
        $chunk = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($chunk));

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->buffer === '';
    }
}
