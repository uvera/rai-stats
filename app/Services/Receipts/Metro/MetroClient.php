<?php

namespace App\Services\Receipts\Metro;

use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use App\Support\JsonBody;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Str;

/**
 * Talks to Metro's "Companion" backend: OAuth2 at idam.metrosystems.net,
 * everything else at api.metronom.dev. Verified against the real Android
 * app's traffic (Companion 3.86/33682) - it is not a generic OAuth client.
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

    private const USER_AGENT = 'Companion/;de.metro.alex.customer.digitalcard.metro (3.86/33682); Google sdk_gphone_x86_64 (Android 11); en_US';

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

        for ($page = 1; $page <= 50; $page++) {
            $body = $this->getJson(
                $accessToken,
                '/companion-finance/v1/invoices?page='.$page.'&locale=en-US'
            );

            $batch = $body['invoices'] ?? [];
            $rows = [...$rows, ...$batch];
            $found = (int) ($body['numFound'] ?? count($rows));

            if ($batch === [] || count($rows) >= $found) {
                break;
            }
        }

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

    /**
     * Every ranked promotion for one store card.
     *
     * Pages follow `nextPage` until it is missing, a page comes back empty,
     * or 40 pages have been read. A 5xx or a timeout is retried twice; if it
     * still fails the call throws and nothing is returned, so the caller can
     * keep the previous cache.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRankedPromotions(string $accessToken, string $country, string $store, string $cardholderId): array
    {
        $country = strtolower($country);
        $articles = [];
        $page = 1;
        $pagesRead = 0;

        while ($pagesRead < 40) {
            $body = $this->getRankedPage($accessToken, $country, $store, $cardholderId, $page);
            $pagesRead++;
            $batch = $body['articleResults'] ?? [];

            if ($batch === []) {
                break;
            }

            $articles = [...$articles, ...$batch];

            if (! isset($body['nextPage']) || $body['nextPage'] === '' || $body['nextPage'] === null) {
                break;
            }

            $page = (int) $body['nextPage'];
        }

        return $articles;
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
            // The edge 403s a token call that has no correlation id. The app
            // sends the same id under both header names.
            'headers' => ['Accept' => 'application/json'] + $this->correlationHeaders(),
            'form_params' => [
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'realm_id' => self::REALM,
                ...$extra,
            ],
        ]);

        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $body = json_decode($raw, true);

        // The edge answers a blocked login with an HTML page. Decoding that
        // as JSON hides the status behind a syntax error.
        if (! is_array($body)) {
            throw new ReceiptException("Metro token request was blocked ({$status}).");
        }

        if (in_array($status, [400, 401], true)) {
            throw new ReceiptAuthException(
                $body['error_description'] ?? $body['error'] ?? 'Metro rejected those credentials.'
            );
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected Metro token status {$status}.");
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
    private function getRankedPage(string $accessToken, string $country, string $store, string $cardholderId, int $page): array
    {
        $path = '/tailored-assortment/v2/'.rawurlencode($country)
            .'/ranked-promotions/store/'.rawurlencode($store)
            .'?locale=en-US&cardholderId='.rawurlencode($cardholderId)
            .'&pageNumber='.$page.'&scope=store';

        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                $response = $this->http->get(self::API.$path, [
                    'headers' => $this->authHeaders($accessToken),
                    'timeout' => 45,
                ]);
            } catch (TransferException) {
                if ($attempts < 3) {
                    continue;
                }

                throw new ReceiptException('Metro promotions request timed out.');
            }

            $status = $response->getStatusCode();

            if ($status === 401) {
                throw new ReceiptAuthException('The stored Metro token is no longer valid.');
            }

            if ($status >= 500 && $status < 600 && $attempts < 3) {
                continue;
            }

            if ($status !== 200) {
                throw new ReceiptException("Unexpected Metro status {$status} for {$path}.");
            }

            return $this->decodeJson((string) $response->getBody());
        }
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
    private function correlationHeaders(): array
    {
        $correlationId = (string) Str::uuid();

        return [
            'X-Correlation-Id' => $correlationId,
            'Calltreeid' => $correlationId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $accessToken): array
    {
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'Accept' => 'application/json, text/plain, */*',
        ] + $this->correlationHeaders();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        return JsonBody::decode($body)
            ?? throw new ReceiptException('Failed to decode Metro JSON response: '.json_last_error_msg());
    }
}
