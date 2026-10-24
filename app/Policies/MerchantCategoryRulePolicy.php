<?php

namespace App\Policies;

use App\Models\MerchantCategoryRule;
use App\Models\User;

class MerchantCategoryRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MerchantCategoryRule $merchantCategoryRule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MerchantCategoryRule $merchantCategoryRule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MerchantCategoryRule $merchantCategoryRule): bool
    {
        return $user->isAdmin();
    }
}
