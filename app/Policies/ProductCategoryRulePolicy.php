<?php

namespace App\Policies;

use App\Models\ProductCategoryRule;
use App\Models\User;

class ProductCategoryRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductCategoryRule $rule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProductCategoryRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProductCategoryRule $rule): bool
    {
        return $user->isAdmin();
    }
}
