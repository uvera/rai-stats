<?php

namespace App\Services\Receipts\Maxi;

use App\Enums\PromotionSource;
use App\Services\Receipts\ReceiptException;

/**
 * Reads the Moj Maxi promotion feeds for one store. HTTP stays on
 * MaxiClient; this class only parses the feeds it is given.
 */
class MaxiPromotions
{
    /**
     * @param  callable(string, string, string): array<string, mixed>  $authorizedGet
     */
    public function __construct(private $authorizedGet) {}

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
                ...$this->tag($source, $this->articlesFrom($this->get(
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
            $body = $this->get(
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
     * @return array<string, mixed>
     */
    private function get(string $path, string $accessToken, string $deviceUuid): array
    {
        return ($this->authorizedGet)($path, $accessToken, $deviceUuid);
    }
}
