<?php

namespace App\Policies;

use App\Models\GroceryAccount;
use App\Models\User;

/**
 * Everyone in the household can see synced Moj Maxi data; only admins add,
 * edit, or remove the accounts themselves.
 */
class GroceryAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GroceryAccount $maxiAccount): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GroceryAccount $maxiAccount): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GroceryAccount $maxiAccount): bool
    {
        return $user->isAdmin();
    }
}
