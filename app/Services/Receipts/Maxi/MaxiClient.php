<?php

namespace App\Services\Receipts\Maxi;

use App\Enums\PromotionSource;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptException;
use App\Support\JsonBody;
use GuzzleHttp\Client;

/**
 * Talks to the "Moj Maxi" mobile loyalty backend
 * (mobileloyalty-prod.delhaize.rs). Every endpoint and header here was
 * verified against the real app's traffic - it matches that specific
 * backend's undocumented shape, it is not a generic client.
 *
 * Note: account calls (login, device registration) live under
 * /prod/A/api/AppEndUser/, invoices under /prod/E/ - same host, different
 * path prefix. App 2.6.1 renamed these off /AppUser/.
 */
class MaxiClient
{
    private const ORIGIN = 'https://mobileloyalty-prod.delhaize.rs';

    /** Hard-coded app key the real client sends on every request (not a secret). */
    private const APP_KEY = '69672f6494e34a33835987cea15baf28';

    private const USER_AGENT = 'okhttp/4.9.2';

    private const APP_VERSION = '2.6.1';

    private const APP_BUILD = '342';

    public const NO_STORE = 'This Moj Maxi account has no store, so promotions cannot be loaded.';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'headers' => ['User-Agent' => self::USER_AGENT],
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    public function login(string $email, string $password, string $deviceUuid): ProviderToken
    {
        $response = $this->http->post(
            self::ORIGIN.'/prod/A/api/AppEndUser/GetAppUserByEmailAndPassword',
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
            throw new ReceiptAuthException(
                $body['Message'] ?? $body['Error'] ?? 'Moj Maxi rejected those credentials.'
            );
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected login status {$status}.");
        }

        $token = $body['Data']['AccessToken'] ?? null;

        // HTTP 200 with Status true and no token is how a rejected login
        // comes back ("Data not found..."), not a transport error.
        if (! is_string($token) || $token === '') {
            throw new ReceiptAuthException(
                $body['Message'] ?? $body['Error'] ?? 'Moj Maxi rejected those credentials.'
            );
        }

        $refresh = $body['Data']['RefreshToken'] ?? null;

        return ProviderToken::fromAccessToken(
            $token,
            is_string($refresh) && $refresh !== '' ? $refresh : null,
        );
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
            self::ORIGIN.'/prod/A/api/AppEndUser/InsertAppUserLoginTracker',
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
            throw new ReceiptAuthException('The stored Moj Maxi token is no longer valid.');
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected InsertAppUserLoginTracker status {$status}.");
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
            throw new ReceiptAuthException('The stored Moj Maxi token is no longer valid.');
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected GetInvoicesByUser status {$status}.");
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
        $parts = parse_url($pdfUrl);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

        if ($scheme !== 'https' || $host !== 'mobileloyalty-prod.delhaize.rs' || isset($parts['user']) || isset($parts['pass'])) {
            throw new ReceiptException('Refusing to download an eReceipt from an unexpected host.');
        }

        $response = $this->http->get($pdfUrl, [
            'headers' => ['User-Agent' => self::USER_AGENT] + $this->baseHeaders($deviceUuid),
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new ReceiptException("Failed to download eReceipt ({$response->getStatusCode()}): {$pdfUrl}");
        }

        return (string) $response->getBody();
    }

    /**
     * The store selected on the signed-in profile. Promotions are priced for it.
     */
    public function storeId(string $accessToken, string $deviceUuid): string
    {
        $body = $this->authorizedGet(
            '/prod/A/api/AppEndUser/GetAuthenticatedAppUser',
            $accessToken,
            $deviceUuid,
        );

        $storeId = $body['Data']['StoreId'] ?? null;

        if (! is_scalar($storeId) || (string) $storeId === '') {
            throw new ReceiptException(self::NO_STORE);
        }

        return (string) $storeId;
    }

    /**
     * Every product promotion the promotions screen loads for one store.
     * Catalog feeds come first, then personalized articles. A failed feed
     * throws and returns nothing, so the caller can keep the previous cache.
     *
     * @return list<array{source: PromotionSource, article: array<string, mixed>}>
     */
    public function listPromotions(string $accessToken, string $deviceUuid, string $storeId): array
    {
        $articles = $this->listTriweekly($accessToken, $deviceUuid, $storeId);

        foreach ([
            [PromotionSource::Weekly, 'GetAvailableWeeklyPromotion'],
            [PromotionSource::Biweekly, 'GetAvailableBiWeeklyPromotion'],
            [PromotionSource::PlusGratis, 'GetAvailablePlusGratisPromotion'],
            [PromotionSource::MaxiBees, 'GetAvailableMaxiBeesPromotion'],
            [PromotionSource::Directed, 'GetAvailableDirectedPromotionByUser'],
        ] as [$source, $action]) {
            $articles = [
                ...$articles,
                ...$this->tag($source, $this->articlesFrom($this->authorizedGet(
                    $this->promotionPath($action, $storeId),
                    $accessToken,
                    $deviceUuid,
                ))),
            ];
        }

        return $articles;
    }

    /**
     * @return list<array{source: PromotionSource, article: array<string, mixed>}>
     */
    private function listTriweekly(string $accessToken, string $deviceUuid, string $storeId): array
    {
        $collected = [];

        for ($page = 0; $page < 50; $page++) {
            $body = $this->authorizedGet(
                '/prod/A/api/AppEndUser/GetAvailableTriWeeklyPromotionPaging'
                    .'?parStoreId='.rawurlencode($storeId)
                    .'&parSearch=&parArticleCategoryId=&parPage='.$page
                    .'&parOrderByColumn=name&parOrderByDirection=asc&parIsIncludeBanner=undefined',
                $accessToken,
                $deviceUuid,
            );
            $this->assertPromotionStatus($body);

            $data = is_array($body['Data'] ?? null) ? $body['Data'] : [];
            $batch = is_array($data['Articles'] ?? null) ? $data['Articles'] : [];

            if ($batch === []) {
                break;
            }

            $collected = [...$collected, ...$this->tag(PromotionSource::Triweekly, $batch)];

            $perPage = (int) ($data['RecordsPerPage'] ?? 20);
            $total = $data['TotalRecords'] ?? null;

            if ($perPage > 0 && count($batch) < $perPage) {
                break;
            }

            if (is_numeric($total) && count($collected) >= (int) $total) {
                break;
            }
        }

        return $collected;
    }

    private function promotionPath(string $action, string $storeId): string
    {
        $query = $action === 'GetAvailableWeeklyPromotion' || $action === 'GetAvailableBiWeeklyPromotion'
            ? 'parStoreId='.rawurlencode($storeId).'&parOrderByColumn=name&parOrderByDirection=asc&parSearch=&parArticleCategoryId='
            : 'parStoreId='.rawurlencode($storeId).'&parSearch=&parArticleCategoryId=&parOrderByColumn=name&parOrderByDirection=asc';

        return '/prod/A/api/AppEndUser/'.$action.'?'.$query;
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizedGet(string $path, string $accessToken, string $deviceUuid): array
    {
        $response = $this->http->get(self::ORIGIN.$path, [
            'headers' => $this->authHeaders($accessToken, $deviceUuid),
        ]);

        $status = $response->getStatusCode();

        if ($status === 401) {
            throw new ReceiptAuthException('The stored Moj Maxi token is no longer valid.');
        }

        if ($status !== 200) {
            throw new ReceiptException("Unexpected Moj Maxi promotions status {$status}.");
        }

        return $this->decodeJson((string) $response->getBody());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function assertPromotionStatus(array $body): void
    {
        if (($body['Status'] ?? true) === false) {
            throw new ReceiptException('Moj Maxi promotions could not be loaded.');
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function articlesFrom(array $body): array
    {
        $this->assertPromotionStatus($body);

        $data = $body['Data'] ?? null;

        if ($data === null) {
            return [];
        }

        if (is_array($data) && isset($data['Articles']) && is_array($data['Articles'])) {
            return array_values(array_filter($data['Articles'], 'is_array'));
        }

        if (! is_array($data) || ! array_is_list($data) || $data === []) {
            return [];
        }

        if (isset($data[0]['Articles']) && is_array($data[0]['Articles'])) {
            $articles = [];

            foreach ($data as $group) {
                if (! is_array($group)) {
                    continue;
                }

                foreach ($group['Articles'] ?? [] as $article) {
                    if (is_array($article)) {
                        $articles[] = $article;
                    }
                }
            }

            return $articles;
        }

        if (isset($data[0]['ArticleId'])) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return list<array{source: PromotionSource, article: array<string, mixed>}>
     */
    private function tag(PromotionSource $source, array $articles): array
    {
        $tagged = [];

        foreach ($articles as $article) {
            if (! is_array($article)) {
                continue;
            }

            $tagged[] = ['source' => $source, 'article' => $article];
        }

        return $tagged;
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
        return JsonBody::decode($body)
            ?? throw new ReceiptException('Failed to decode Moj Maxi JSON response: '.json_last_error_msg());
    }
}
