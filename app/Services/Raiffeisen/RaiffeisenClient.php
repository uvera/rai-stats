<?php

namespace App\Services\Raiffeisen;

use App\Services\Raiffeisen\Data\AccountBalance;
use App\Services\Raiffeisen\Data\LoginResult;
use App\Services\Raiffeisen\Data\PushLoginResult;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction;
use App\Support\JsonBody;
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

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0';

    private Client $http;

    private CookieJar $cookieJar;

    /** @var callable(string, string, array, string): RawSseStream */
    private $sseStreamFactory;

    public function __construct(
        private readonly Argon2iHasher $hasher = new Argon2iHasher,
        ?Client $http = null,
        ?CookieJar $cookieJar = null,
        ?callable $sseStreamFactory = null,
    ) {
        $this->cookieJar = $cookieJar ?? new CookieJar;
        $this->http = $http ?? new Client([
            'cookies' => $this->cookieJar,
            'headers' => ['User-Agent' => self::USER_AGENT],
            'http_errors' => false,
        ]);
        $this->sseStreamFactory = $sseStreamFactory ?? fn (string $host, string $path, array $headers, string $cookieHeader) => new RawSseStream($host, $path, $headers, $cookieHeader);
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
            throw new RaiffeisenException("Unexpected LoginFont status {$response->getStatusCode()}.");
        }

        $data = $this->decodeJson($response->getBody()->getContents());

        if (empty($data['Ticket'])) {
            throw new RaiffeisenException(
                $data['ErrorMessage'] ?? $data['Message'] ?? 'Login failed - check your username and password.'
            );
        }

        return LoginResult::fromArray($data);
    }

    /**
     * Runs the SignalR long-poll handshake and waits (up to $timeoutSeconds)
     * for the user to approve the push notification on their phone.
     */
    public function requestLoginPush(string $ticket, string $username, int $timeoutSeconds = 180): PushLoginResult
    {
        return $this->pushLogin()->requestLoginPush($ticket, $username, $timeoutSeconds);
    }

    public function loginUPPush(string $firstStepTicket, string $pushRequestContent, int $sessionId): void
    {
        $this->pushLogin()->loginUPPush($firstStepTicket, $pushRequestContent, $sessionId);
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

    private function pushLogin(): RaiffeisenPushLogin
    {
        return new RaiffeisenPushLogin($this->http, $this->cookieJar, $this->sseStreamFactory);
    }

    private function decodeJson(string $body): array
    {
        return JsonBody::decode($body)
            ?? throw new RaiffeisenException('Failed to decode JSON response: '.json_last_error_msg());
    }
}
