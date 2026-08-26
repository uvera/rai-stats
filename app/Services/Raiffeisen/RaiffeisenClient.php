<?php

namespace App\Services\Raiffeisen;

use App\Services\Raiffeisen\Data\AccountBalance;
use App\Services\Raiffeisen\Data\LoginResult;
use App\Services\Raiffeisen\Data\PushLoginResult;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Ports raiffeisen-retail-api's client.go to PHP: logs into RaiOnline (the
 * Raiffeisen Serbia retail internet banking backend) and fetches account
 * balances / transaction turnover, including the mobile push 2FA flow.
 *
 * Every endpoint, field index, and sign convention here was verified against
 * the real bank during earlier ad-hoc analysis (see project history) - this
 * is not a generic client, it matches this specific backend's undocumented
 * shape exactly.
 */
class RaiffeisenClient
{
    private const ORIGIN = 'https://rol.raiffeisenbank.rs';

    private const REFERER = self::ORIGIN.'/Retail/Home/Login';

    private const SIGNALR_BASE_URL = self::ORIGIN.'/Retail/signalr';

    private const SIGNALR_PROTOCOL = '2.1';

    private const IBANKING_HUB_DATA = '[{"name":"ibankinghub"}]';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0';

    private Client $http;

    private CookieJar $cookieJar;

    public function __construct(
        private readonly Argon2iHasher $hasher = new Argon2iHasher,
        ?Client $http = null,
        ?CookieJar $cookieJar = null,
    ) {
        $this->cookieJar = $cookieJar ?? new CookieJar;
        $this->http = $http ?? new Client([
            'cookies' => $this->cookieJar,
            'headers' => ['User-Agent' => self::USER_AGENT],
            'http_errors' => false,
        ]);
    }

    /**
     * Restores a previously authenticated session (see exportCookies()) so a
     * later request can reuse the login without repeating the 2FA flow.
     */
    public static function withCookies(array $cookieData): self
    {
        return new self(cookieJar: new CookieJar(false, $cookieData));
    }

    /**
     * @return array Serializable cookie data, restorable via withCookies().
     */
    public function exportCookies(): array
    {
        return $this->cookieJar->toArray();
    }

    public function login(): void
    {
        $this->http->get(self::ORIGIN.'/Retail/Home/Login');
    }

    public function loginFont(string $username, string $password): LoginResult
    {
        $hashedPassword = $this->hasher->hash($username, $password);

        $response = $this->http->post(
            self::ORIGIN.'/Retail/Protected/Services/RetailLoginService.svc/LoginFont',
            [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'username' => $username,
                    'password' => $hashedPassword,
                    'sessionID' => 1,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("Unexpected LoginFont status {$response->getStatusCode()}: {$response->getBody()}");
        }

        return LoginResult::fromArray($this->decodeJson($response->getBody()->getContents()));
    }

    /**
     * Runs the SignalR long-poll handshake and waits (up to $timeoutSeconds)
     * for the user to approve the push notification on their phone.
     */
    public function requestLoginPush(string $ticket, string $username, int $timeoutSeconds = 180): PushLoginResult
    {
        $token = $this->signalRNegotiate();

        $stream = $this->signalRConnectStream($token);

        $deadline = microtime(true) + $timeoutSeconds;
        $buffer = '';

        // Pulls the next complete "data: ..." SSE line, buffering any partial
        // trailing line across reads. Drains what's already buffered before
        // touching the stream again - a line read into $buffer while looking
        // for one thing (the ready signal) must still be seen when looking
        // for the next thing (the push result), even if the stream itself
        // has no more bytes left to give by then.
        $nextDataLine = function () use ($stream, &$buffer, $deadline): ?string {
            while (true) {
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (str_starts_with($line, 'data: ')) {
                        return substr($line, strlen('data: '));
                    }
                }

                if ($stream->eof()) {
                    return null;
                }

                if (microtime(true) > $deadline) {
                    throw new RaiffeisenException('Timed out reading SignalR stream');
                }

                $buffer .= $stream->read(4096);
            }
        };

        while (true) {
            $payload = $nextDataLine();
            if ($payload === null) {
                throw new RaiffeisenException('SignalR stream closed before becoming ready');
            }

            if (trim($payload) === 'initialized') {
                break;
            }

            $envelope = json_decode($payload, true);
            if (is_array($envelope) && ($envelope['S'] ?? null) === 1) {
                break;
            }
        }

        $this->signalRStart($token);
        $this->signalRSend($token, $ticket, $username);

        while (true) {
            $payload = $nextDataLine();
            if ($payload === null) {
                throw new RaiffeisenException('SignalR stream closed before push was approved');
            }

            if (trim($payload) === '' || trim($payload) === 'initialized') {
                continue;
            }

            $envelope = json_decode($payload, true);
            if (! is_array($envelope)) {
                continue;
            }

            if (! empty($envelope['E'])) {
                throw new RaiffeisenException("SignalR hub error: {$envelope['E']}");
            }

            foreach ($envelope['M'] ?? [] as $invocation) {
                if (strcasecmp($invocation['M'] ?? '', 'LoginUPRequestApproved') !== 0) {
                    continue;
                }

                $args = $invocation['A'] ?? [];
                if (empty($args)) {
                    throw new RaiffeisenException('LoginUPRequestApproved with no args');
                }

                $result = PushLoginResult::fromArray($args[0]);

                if (! $result->isApproved()) {
                    throw new RaiffeisenException("Login push {$result->status}");
                }

                return $result;
            }
        }
    }

    public function loginUPPush(string $firstStepTicket, string $pushRequestContent, int $sessionId): void
    {
        $response = $this->http->post(
            self::ORIGIN.'/Retail/Protected/Services/RetailLoginService.svc/LoginUPPush',
            [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'firstStepTicket' => $firstStepTicket,
                    'pushRequestContent' => $pushRequestContent,
                    'sessionID' => $sessionId,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("Unexpected LoginUPPush status {$response->getStatusCode()}: {$response->getBody()}");
        }
    }

    /**
     * @return AccountBalance[]
     */
    public function allAccountBalance(): array
    {
        $response = $this->http->post(
            self::ORIGIN.'/Retail/Protected/Services/DataService.svc/GetAllAccountBalance',
            [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => ['gridName' => 'RetailAccountBalancePreviewFlat-L'],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("Unexpected status code: {$response->getStatusCode()}");
        }

        $rows = $this->decodeJson($response->getBody()->getContents());

        return array_map(fn (array $row) => AccountBalance::fromRow($row), $rows);
    }

    /**
     * @return Transaction[]
     */
    public function transactionalAccountTurnover(
        string $productCoreId,
        string $accountNumber,
        string $currencyCodeNumeric,
        string $fromDate,
        string $toDate,
    ): array {
        $response = $this->http->post(
            self::ORIGIN.'/Retail/Protected/Services/DataService.svc/GetTransactionalAccountTurnover',
            [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'accountNumber' => $accountNumber,
                    'filterParam' => [
                        'CurrencyCodeNumeric' => $currencyCodeNumeric,
                        'FromDate' => $fromDate,
                        'ToDate' => $toDate,
                        'ItemType' => '',
                        'ItemCount' => '',
                        'FromAmount' => '',
                        'ToAmount' => '',
                        'PaymentPurpose' => '',
                    ],
                    'gridName' => 'RetailAccountTurnoverTransactionPreviewMasterDetail-S',
                    'productCoreID' => $productCoreId,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("Unexpected status code: {$response->getStatusCode()}");
        }

        $response = $this->decodeJson($response->getBody()->getContents());

        if (empty($response)) {
            return [];
        }

        $rows = $response[0][1] ?? [];

        return array_map(fn (array $row) => Transaction::fromRow($row), $rows);
    }

    /**
     * @return ReservedTransaction[]
     */
    public function transactionalAccountReservedFunds(string $accountNumber): array
    {
        $response = $this->http->post(
            self::ORIGIN.'/Retail/Protected/Services/DataService.svc/GetTransactionalAccountReservedFunds',
            [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'accountNumber' => $accountNumber,
                    'gridName' => 'RetailAccountReservedFundsPreviewFlat',
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("Unexpected status code: {$response->getStatusCode()}");
        }

        $rows = $this->decodeJson($response->getBody()->getContents());

        return array_map(fn (array $row) => ReservedTransaction::fromRow($row), $rows);
    }

    private function signalRNegotiate(): string
    {
        $url = self::SIGNALR_BASE_URL.'/negotiate?'.http_build_query([
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionData' => self::IBANKING_HUB_DATA,
            '_' => (int) (microtime(true) * 1000),
        ]);

        $response = $this->http->get($url, [
            'headers' => ['Origin' => self::ORIGIN, 'Referer' => self::REFERER],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("negotiate status {$response->getStatusCode()}: {$response->getBody()}");
        }

        $data = json_decode($response->getBody()->getContents(), true);
        $token = $data['ConnectionToken'] ?? '';

        if ($token === '') {
            throw new RaiffeisenException('empty connection token');
        }

        return $token;
    }

    private function signalRConnectStream(string $token)
    {
        $url = self::SIGNALR_BASE_URL.'/connect?'.http_build_query([
            'transport' => 'serverSentEvents',
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionToken' => $token,
            'connectionData' => self::IBANKING_HUB_DATA,
            'tid' => random_int(0, 9),
        ]);

        $response = $this->http->get($url, [
            'headers' => [
                'Accept' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Origin' => self::ORIGIN,
                'Referer' => self::REFERER,
            ],
            'stream' => true,
            'read_timeout' => 200,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("connect status {$response->getStatusCode()}: {$response->getBody()}");
        }

        return $response->getBody();
    }

    private function signalRStart(string $token): void
    {
        $url = self::SIGNALR_BASE_URL.'/start?'.http_build_query([
            'transport' => 'serverSentEvents',
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionToken' => $token,
            'connectionData' => self::IBANKING_HUB_DATA,
            '_' => (int) (microtime(true) * 1000),
        ]);

        $response = $this->http->get($url, [
            'headers' => ['Origin' => self::ORIGIN, 'Referer' => self::REFERER],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("start status {$response->getStatusCode()}: {$response->getBody()}");
        }
    }

    private function signalRSend(string $token, string $ticket, string $username): void
    {
        $data = json_encode([
            'H' => 'ibankinghub',
            'M' => 'CreateLoginPushRequest',
            'A' => [$ticket, $username],
            'I' => 0,
        ]);

        $url = self::SIGNALR_BASE_URL.'/send?'.http_build_query([
            'transport' => 'serverSentEvents',
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionToken' => $token,
            'connectionData' => self::IBANKING_HUB_DATA,
        ]);

        $response = $this->http->post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Origin' => self::ORIGIN,
                'Referer' => self::REFERER,
            ],
            'form_params' => ['data' => $data],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("send status {$response->getStatusCode()}: {$response->getBody()}");
        }
    }

    private function decodeJson(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RaiffeisenException('Failed to decode JSON response: '.json_last_error_msg());
        }

        return $decoded;
    }
}
