<?php

namespace App\Policies;

use App\Models\MaxiAccount;
use App\Models\User;

/**
 * Everyone in the household can see synced Moj Maxi data; only admins add,
 * edit, or remove the accounts themselves.
 */
class MaxiAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaxiAccount $maxiAccount): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MaxiAccount $maxiAccount): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MaxiAccount $maxiAccount): bool
    {
        return $user->isAdmin();
    }
}
