<?php

namespace App\Services\Receipts\Metro;

use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

/**
 * Talks to Metro's "Companion" backend: OAuth2 at idam.metrosystems.net,
 * everything else at api.metronom.dev. Verified against the real Android
 * app's traffic (build 3.83.1) - it is not a generic OAuth client.
 *
 * Unlike Moj Maxi the access token lives only ~1 hour, so callers are
 * expected to keep the refresh token and call refresh() on every sync.
 */
class MetroClient
{
    private const IDAM = 'https://idam.metrosystems.net';

    private const API = 'https://api.metronom.dev';

    /** App credentials the real client embeds (not user secrets). */
    private const CLIENT_ID = 'MCOMPANION_ANDROID_361';

    private const CLIENT_SECRET = '7h6WeseDae';

    /** Realm for RS retail customers. */
    private const REALM = 'ALEX_REALM';

    private const USER_AGENT = 'Companion/;de.metro.alex.customer.digitalcard.metro (3.83.1/33604); Google sdk_gphone_x86_64 (Android 13)';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'headers' => ['User-Agent' => self::USER_AGENT],
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    public function login(string $email, string $password): ProviderToken
    {
        return $this->token([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
            'user_type' => 'CUST',
        ]);
    }

    public function refresh(string $refreshToken): ProviderToken
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * The customer's loyalty card id (e.g. "RS_22_760829_1"), or null if the
     * account has no card yet.
     */
    public function cardholderId(string $accessToken): ?string
    {
        $body = $this->getJson($accessToken, '/companion-card/v1/person/details');

        return $body['cards'][0]['cardholderId'] ?? null;
    }

    /**
     * Every invoice row across all pages (raw, provider-shaped).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listInvoices(string $accessToken): array
    {
        $rows = [];
        $page = 1;

        do {
            $body = $this->getJson(
                $accessToken,
                '/companion-finance/v1/invoices?page='.$page.'&locale=en-US'
            );

            $batch = $body['invoices'] ?? [];
            $rows = [...$rows, ...$batch];
            $found = (int) ($body['numFound'] ?? count($rows));
            $page++;
        } while ($batch !== [] && count($rows) < $found);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchArticles(string $accessToken, string $transactionId): array
    {
        $body = $this->getJson(
            $accessToken,
            '/companion-finance/v1/invoices/'.rawurlencode($transactionId).'/articles'
        );

        return $body['articles'] ?? [];
    }

    public function downloadPdf(string $accessToken, string $transactionId): string
    {
        $response = $this->http->get(
            self::API.'/companion-finance/v1/invoices/'.rawurlencode($transactionId).'/pdf',
            ['headers' => $this->authHeaders($accessToken)]
        );

        $status = $response->getStatusCode();

        if ($status === 401) {
            throw new ReceiptAuthException('The stored Metro token is no longer valid.');
        }

        if ($status !== 200) {
            throw new ReceiptException("Failed to download Metro receipt ({$status}): {$transactionId}");
        }

        return (string) $response->getBody();
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function token(array $extra): ProviderToken
    {
        $response = $this->http->post(self::IDAM.'/authorize/api/oauth2/access_token', [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'realm_id' => self::REALM,
                ...$extra,
            ],
        ]);

        $status = $response->getStatusCode();
        $body = $this->decodeJson((string) $response->getBody());

        if (in_array($status, [400, 401], true)) {
            throw new ReceiptAuthException(
                $body['error_description'] ?? $body['error'] ?? 'Metro rejected those credentials.'
            );
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected Metro token status {$status}: ".substr((string) $response->getBody(), 0, 300));
        }

        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ReceiptException('Metro login succeeded but returned no access token.');
        }

        return ProviderToken::fromAccessToken($token, $body['refresh_token'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $accessToken, string $path): array
    {
        $response = $this->http->get(self::API.$path, ['headers' => $this->authHeaders($accessToken)]);
        $status = $response->getStatusCode();

        if ($status === 401) {
            throw new ReceiptAuthException('The stored Metro token is no longer valid.');
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected Metro status {$status} for {$path}.");
        }

        return $this->decodeJson((string) $response->getBody());
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $accessToken): array
    {
        $correlationId = (string) Str::uuid();

        return [
            'Authorization' => 'Bearer '.$accessToken,
            'Accept' => 'application/json, text/plain, */*',
            'X-Correlation-Id' => $correlationId,
            'Calltreeid' => $correlationId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new ReceiptException('Failed to decode Metro JSON response: '.json_last_error_msg());
        }

        return $decoded;
    }
}
