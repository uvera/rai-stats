<?php

namespace App\Policies;

use App\Models\MetroPromotion;
use App\Models\User;

/**
 * The cached Metro catalogue is household data, like receipts.
 */
class MetroPromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MetroPromotion $promotion): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MetroPromotion $promotion): bool
    {
        return false;
    }

    public function delete(User $user, MetroPromotion $promotion): bool
    {
        return false;
    }
}
