<?php

namespace Tests\Feature\Maxi;

use App\Models\MaxiAccount;
use App\Models\MaxiReceipt;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaxiPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_may_manage_maxi_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $account = MaxiAccount::factory()->create();

        $this->assertTrue($user->can('view', $account));
        $this->assertFalse($user->can('create', MaxiAccount::class));
        $this->assertFalse($user->can('update', $account));
        $this->assertFalse($user->can('delete', $account));

        $this->assertTrue($admin->can('create', MaxiAccount::class));
        $this->assertTrue($admin->can('update', $account));
        $this->assertTrue($admin->can('delete', $account));
    }

    public function test_receipts_are_read_only_for_non_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $receipt = MaxiReceipt::factory()->create();

        $this->assertTrue($user->can('view', $receipt));
        $this->assertFalse($user->can('update', $receipt));
        $this->assertFalse($user->can('create', MaxiReceipt::class));

        $this->assertTrue($admin->can('update', $receipt));
        $this->assertFalse($admin->can('create', MaxiReceipt::class));
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
