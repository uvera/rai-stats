<?php

namespace App\Services\Receipts;

use App\Models\GroceryAccount;
use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\Data\ProviderToken;

/**
 * Unattended sign-in for a grocery account: a live access token, else a
 * refresh, else the stored password. A one-shot password typed into the
 * sync form stays in the receipt job; this class never prompts.
 */
class GroceryAccountAuthenticator
{
    public function unattended(GroceryAccount $account, ProviderClient $client): ?ProviderToken
    {
        if ($account->tokenValid()) {
            return new ProviderToken(
                $account->access_token,
                $account->token_expires_at->toImmutable(),
                $account->refresh_token,
            );
        }

        if (($refreshed = $client->refresh($account)) !== null) {
            return $this->persist($account, $refreshed);
        }

        if (blank($account->password)) {
            return null;
        }

        return $this->persist($account, $client->login($account, $account->password));
    }

    public function persist(GroceryAccount $account, ProviderToken $token): ProviderToken
    {
        $account->forceFill([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?? $account->refresh_token,
            'token_expires_at' => $token->expiresAt,
        ])->save();

        return $token;
    }
}
