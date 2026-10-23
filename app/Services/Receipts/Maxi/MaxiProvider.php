<?php

namespace App\Services\Receipts\Maxi;

use App\Models\GroceryAccount;
use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\Data\FetchedReceipt;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\ReceiptPdfParser;

/**
 * Moj Maxi behind the shared ProviderClient contract. Line items come from
 * the eReceipt PDF's text layer (ReceiptPdfParser). Login returns a
 * short-lived access JWT plus an opaque refresh token; no refresh call was
 * observed, so refresh() stays null and an expired token logs in again with
 * the stored password.
 */
class MaxiProvider implements ProviderClient
{
    public function __construct(
        private readonly MaxiClient $client = new MaxiClient,
        private readonly ReceiptPdfParser $parser = new ReceiptPdfParser,
    ) {}

    public function login(GroceryAccount $account, string $password): ProviderToken
    {
        return $this->client->login($account->email, $password, $account->device_uuid);
    }

    public function refresh(GroceryAccount $account): ?ProviderToken
    {
        return null;
    }

    public function listInvoices(GroceryAccount $account, ProviderToken $token): array
    {
        // The invoice endpoint 406s unless this device has been registered
        // against the account at least once - idempotent, so do it every sync.
        $this->client->registerDevice($token->accessToken, $account->device_uuid);

        return $this->client->listInvoices($token->accessToken, $account->device_uuid);
    }

    public function fetchReceipt(GroceryAccount $account, ProviderToken $token, InvoiceSummary $summary): FetchedReceipt
    {
        $pdf = $this->client->downloadReceipt($summary->pdfUrl, $account->device_uuid);

        return new FetchedReceipt($this->parser->parse($pdf), $pdf);
    }
}
