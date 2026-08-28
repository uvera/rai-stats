<?php

namespace App\Services\Receipts\Metro;

use App\Models\GroceryAccount;
use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\Data\FetchedReceipt;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ParsedItem;
use App\Services\Receipts\Data\ParsedReceipt;
use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\ReceiptAuthException;
use App\Services\Receipts\ReceiptPdfParser;
use Throwable;

/**
 * Metro behind the shared ProviderClient contract. Line items come from a
 * structured JSON feed (no PDF scraping); the PDF is still downloaded so the
 * receipt keeps a human-readable raw copy.
 */
class MetroProvider implements ProviderClient
{
    public function __construct(
        private readonly MetroClient $client = new MetroClient,
        private readonly ReceiptPdfParser $parser = new ReceiptPdfParser,
    ) {}

    public function login(GroceryAccount $account, string $password): ProviderToken
    {
        return $this->client->login($account->email, $password);
    }

    public function refresh(GroceryAccount $account): ?ProviderToken
    {
        if (blank($account->refresh_token)) {
            return null;
        }

        try {
            return $this->client->refresh($account->refresh_token);
        } catch (ReceiptAuthException) {
            return null;
        }
    }

    public function listInvoices(GroceryAccount $account, ProviderToken $token): array
    {
        return array_map(
            fn (array $row) => InvoiceSummary::fromMetroRow($row),
            $this->client->listInvoices($token->accessToken)
        );
    }

    public function fetchReceipt(GroceryAccount $account, ProviderToken $token, InvoiceSummary $summary): FetchedReceipt
    {
        $articles = $this->client->fetchArticles($token->accessToken, $summary->remoteId ?? $summary->externalRef);

        $items = [];
        foreach (array_values($articles) as $i => $article) {
            $gross = (int) round(((float) ($article['grossItemTotal'] ?? 0)) * 100);
            $net = (int) round(((float) ($article['netItemTotal'] ?? 0)) * 100);

            $items[] = new ParsedItem(
                lineNo: $i + 1,
                name: trim((string) ($article['description'] ?? '')),
                quantity: (float) ($article['quantity'] ?? 1),
                unitPriceCents: (int) round(((float) ($article['grossItemPrice'] ?? 0)) * 100),
                totalCents: $gross,
                vatRate: $net > 0 ? round(($gross / $net - 1) * 100, 2) : null,
                netUnitPriceCents: (int) round(((float) ($article['netItemPrice'] ?? 0)) * 100),
                netTotalCents: $net,
            );
        }

        $pdf = $this->safePdf($token->accessToken, $summary->remoteId ?? $summary->externalRef);

        $parsed = new ParsedReceipt(
            items: $items,
            vatLines: [],
            pfrNumber: null,
            pursVl: null,
            totalCents: $summary->totalCents,
            rawText: $pdf !== null ? $this->safeText($pdf) : '',
        );

        return new FetchedReceipt($parsed, $pdf);
    }

    private function safePdf(string $accessToken, string $transactionId): ?string
    {
        try {
            return $this->client->downloadPdf($accessToken, $transactionId);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeText(string $pdfBytes): string
    {
        try {
            return $this->parser->extractText($pdfBytes);
        } catch (Throwable) {
            return '';
        }
    }
}
