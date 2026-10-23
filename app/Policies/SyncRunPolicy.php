<?php

namespace App\Policies;

use App\Enums\SyncKind;
use App\Models\SyncRun;
use App\Models\User;

/**
 * A person always sees runs they started. Grocery syncs are household
 * infrastructure, so every admin can read them. Bank login and import
 * reports stay with the user who ran them.
 */
class SyncRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SyncRun $syncRun): bool
    {
        if ($syncRun->user_id === $user->id) {
            return true;
        }

        return $user->isAdmin() && $syncRun->kind === SyncKind::GrocerySync;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SyncRun $syncRun): bool
    {
        return false;
    }

    public function delete(User $user, SyncRun $syncRun): bool
    {
        return false;
    }
}
