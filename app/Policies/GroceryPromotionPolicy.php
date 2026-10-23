<?php

namespace App\Policies;

use App\Models\GroceryPromotion;
use App\Models\User;

/**
 * The cached promotion catalogue is household data, like receipts.
 */
class GroceryPromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GroceryPromotion $promotion): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GroceryPromotion $promotion): bool
    {
        return false;
    }

    public function delete(User $user, GroceryPromotion $promotion): bool
    {
        return false;
    }
}
