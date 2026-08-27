<?php

namespace App\Services\Maxi;

use App\Services\Maxi\Data\InvoiceSummary;
use App\Services\Maxi\Data\MaxiLoginResult;
use GuzzleHttp\Client;

/**
 * Talks to the "Moj Maxi" mobile loyalty backend
 * (mobileloyalty-prod.delhaize.rs). Every endpoint and header here was
 * verified against the real app's traffic - it matches that specific
 * backend's undocumented shape, it is not a generic client.
 *
 * Note: login lives under /prod/A/, invoices under /prod/E/ - same host,
 * different path prefix.
 */
class MaxiClient
{
    private const ORIGIN = 'https://mobileloyalty-prod.delhaize.rs';

    /** Hard-coded app key the real client sends on every request (not a secret). */
    private const APP_KEY = '07ef3b5a079f4f1a8f846f043adddbb8';

    private const USER_AGENT = 'okhttp/4.9.2';

    private const APP_VERSION = '2.5.6';

    private const APP_BUILD = '338';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'headers' => ['User-Agent' => self::USER_AGENT],
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    public function login(string $email, string $password, string $deviceUuid): MaxiLoginResult
    {
        $response = $this->http->post(
            self::ORIGIN.'/prod/A/api/AppUser/GetAppUserByEmailAndPassword',
            [
                'headers' => $this->baseHeaders($deviceUuid) + ['Content-Type' => 'application/json'],
                'json' => [
                    'Email' => $email,
                    'Password' => $password,
                    'AppLanguage' => ['Id' => 1],
                ],
            ]
        );

        $status = $response->getStatusCode();
        $body = $this->decodeJson((string) $response->getBody());

        if ($status === 401 || ($body['Status'] ?? null) === false) {
            throw new MaxiAuthException(
                $body['Message'] ?? $body['Error'] ?? 'Moj Maxi rejected those credentials.'
            );
        }

        if ($status !== 200) {
            throw new MaxiException("Unexpected login status {$status}: ".substr((string) $response->getBody(), 0, 300));
        }

        $token = $body['Data']['AccessToken'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new MaxiException('Login succeeded but no access token was returned.');
        }

        return MaxiLoginResult::fromAccessToken($token);
    }

    /**
     * Registers this device against the account. The invoice endpoints 406
     * unless the caller's deviceuuid has been through here at least once
     * (the mobile app calls it on every login), so a sync runs it first.
     * Idempotent - each call just records another login-tracker row.
     */
    public function registerDevice(string $accessToken, string $deviceUuid): void
    {
        $response = $this->http->post(
            self::ORIGIN.'/prod/A/api/AppUser/InsertAppUserLoginTracker',
            [
                'headers' => $this->authHeaders($accessToken, $deviceUuid) + ['Content-Type' => 'application/json'],
                'json' => [
                    'DeviceUuid' => $deviceUuid,
                    'DeviceOs' => 'Android',
                    'DeviceOsVersion' => '13',
                    'DeviceBrand' => 'google',
                    'DeviceModel' => 'Pixel',
                    'Version' => self::APP_VERSION,
                    'BuildNumber' => self::APP_BUILD,
                ],
            ]
        );

        $status = $response->getStatusCode();

        if ($status === 401) {
            throw new MaxiAuthException('The stored Moj Maxi token is no longer valid.');
        }

        if ($status !== 200) {
            throw new MaxiException("Unexpected InsertAppUserLoginTracker status {$status}.");
        }
    }

    /**
     * @return InvoiceSummary[]
     */
    public function listInvoices(string $accessToken, string $deviceUuid): array
    {
        $response = $this->http->get(
            self::ORIGIN.'/prod/E/api/Invoice/GetInvoicesByUser',
            ['headers' => $this->authHeaders($accessToken, $deviceUuid)]
        );

        $status = $response->getStatusCode();

        if ($status === 401) {
            throw new MaxiAuthException('The stored Moj Maxi token is no longer valid.');
        }

        if ($status !== 200) {
            throw new MaxiException("Unexpected GetInvoicesByUser status {$status}.");
        }

        $body = $this->decodeJson((string) $response->getBody());
        $rows = $body['Data'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row) => InvoiceSummary::fromRow($row), $rows);
    }

    /**
     * Downloads an eReceipt PDF. The URL carries its own opaque hash and
     * needs no auth, but we send the standard headers anyway for consistency.
     */
    public function downloadReceipt(string $pdfUrl, string $deviceUuid): string
    {
        $response = $this->http->get($pdfUrl, [
            'headers' => ['User-Agent' => self::USER_AGENT] + $this->baseHeaders($deviceUuid),
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new MaxiException("Failed to download eReceipt ({$response->getStatusCode()}): {$pdfUrl}");
        }

        return (string) $response->getBody();
    }

    /**
     * @return array<string, string>
     */
    private function baseHeaders(string $deviceUuid): array
    {
        return [
            'delhaize' => self::APP_KEY,
            'deviceuuid' => $deviceUuid,
            'Accept' => 'application/json, text/plain, */*',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $accessToken, string $deviceUuid): array
    {
        return $this->baseHeaders($deviceUuid) + ['Authorization' => 'Bearer '.$accessToken];
    }

    private function decodeJson(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new MaxiException('Failed to decode Moj Maxi JSON response: '.json_last_error_msg());
        }

        return $decoded;
    }
}
