<?php

namespace App\Policies;

use App\Models\GroceryReceipt;
use App\Models\User;

class GroceryReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GroceryReceipt $groceryReceipt): bool
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
    public function update(User $user, GroceryReceipt $groceryReceipt): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GroceryReceipt $groceryReceipt): bool
    {
        return $user->isAdmin();
    }
}
