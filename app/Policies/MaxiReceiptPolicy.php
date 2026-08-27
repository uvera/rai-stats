<?php

namespace App\Policies;

use App\Models\MaxiReceipt;
use App\Models\User;

class MaxiReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaxiReceipt $maxiReceipt): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Receipts only ever arrive through sync.
        return false;
    }

    /**
     * Linking a receipt to a transaction and categorising its items.
     */
    public function update(User $user, MaxiReceipt $maxiReceipt): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MaxiReceipt $maxiReceipt): bool
    {
        return $user->isAdmin();
    }
}
