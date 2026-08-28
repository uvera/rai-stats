<?php

namespace App\Services\Receipts\Contracts;

use App\Models\GroceryAccount;
use App\Services\Receipts\Data\FetchedReceipt;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ProviderToken;

/**
 * One grocery receipt backend (Moj Maxi, Metro, ...). Implementations own all
 * of the provider's HTTP quirks; SyncGroceryAccountJob and ReceiptImporter
 * only ever see these four calls and the shared DTOs.
 */
interface ProviderClient
{
    /**
     * Exchange the account's credentials for a fresh session.
     */
    public function login(GroceryAccount $account, string $password): ProviderToken;

    /**
     * Silently renew the session from a stored refresh token, or null when the
     * provider has no refresh mechanism or the refresh token is no longer
     * accepted (the caller then falls back to a password login).
     */
    public function refresh(GroceryAccount $account): ?ProviderToken;

    /**
     * @return InvoiceSummary[]
     */
    public function listInvoices(GroceryAccount $account, ProviderToken $token): array;

    public function fetchReceipt(GroceryAccount $account, ProviderToken $token, InvoiceSummary $summary): FetchedReceipt;
}
