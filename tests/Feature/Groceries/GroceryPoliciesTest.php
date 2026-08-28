<?php

namespace Tests\Feature\Groceries;

use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroceryPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_may_manage_grocery_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $account = GroceryAccount::factory()->create();

        $this->assertTrue($user->can('view', $account));
        $this->assertFalse($user->can('create', GroceryAccount::class));
        $this->assertFalse($user->can('update', $account));
        $this->assertFalse($user->can('delete', $account));

        $this->assertTrue($admin->can('create', GroceryAccount::class));
        $this->assertTrue($admin->can('update', $account));
        $this->assertTrue($admin->can('delete', $account));
    }

    public function test_receipts_are_read_only_for_non_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $receipt = GroceryReceipt::factory()->create();

        $this->assertTrue($user->can('view', $receipt));
        $this->assertFalse($user->can('update', $receipt));
        $this->assertFalse($user->can('create', GroceryReceipt::class));

        $this->assertTrue($admin->can('update', $receipt));
        $this->assertFalse($admin->can('create', GroceryReceipt::class));
    }

    public function test_only_admins_may_manage_product_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create();

        $this->assertTrue($user->can('view', $category));
        $this->assertFalse($user->can('update', $category));
        $this->assertTrue($admin->can('update', $category));
    }
}
