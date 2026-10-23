<?php

namespace App\Policies;

use App\Models\MetroPromotionMatch;
use App\Models\User;

/**
 * Promotions are household data, like receipts. Nothing here is edited by hand.
 */
class MetroPromotionMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MetroPromotionMatch $match): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MetroPromotionMatch $match): bool
    {
        return false;
    }

    public function delete(User $user, MetroPromotionMatch $match): bool
    {
        return false;
    }
}
