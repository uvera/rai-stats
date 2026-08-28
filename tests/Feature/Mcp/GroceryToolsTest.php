<?php

namespace Tests\Feature\Mcp;

use App\Enums\CategorySource;
use App\Enums\ReceiptProvider;
use App\Enums\TokenScope;
use App\Mcp\Servers\RaiStatsServer;
use App\Mcp\Tools\ListProductCategoriesTool;
use App\Mcp\Tools\ListReceiptItemsTool;
use App\Mcp\Tools\ListUncategorizedReceiptItemsTool;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class GroceryToolsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(User $user, TokenScope $scope): User
    {
        return $user->withAccessToken(
            $user->createToken('t', [$scope->ability()])->accessToken
        );
    }

    private function item(User $owner, array $attributes = []): GroceryReceiptItem
    {
        $account = GroceryAccount::factory()->for($owner)->create();
        $receipt = GroceryReceipt::factory()->for($account, 'account')->create();

        return GroceryReceiptItem::factory()->for($receipt, 'receipt')->create($attributes);
    }

    public function test_lists_product_categories_with_rules_and_counts(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Mlečni proizvodi']);
        $category->rules()->create(['pattern' => 'mleko', 'priority' => 5]);
        $me = User::factory()->create();
        $this->item($me, ['name' => 'MLEKO 2.8%', 'product_category_id' => $category->id]);

        RaiStatsServer::actingAs($this->actingUser($me, TokenScope::Self))
            ->tool(ListProductCategoriesTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('categories', 1)
                ->where('categories.0.name', 'Mlečni proizvodi')
                ->where('categories.0.item_count', 1)
                ->where('categories.0.rules.0.pattern', 'mleko')
            );
    }

    public function test_uncategorized_items_are_aggregated_and_scoped(): void
    {
        $me = User::factory()->create();
        $sibling = User::factory()->create();

        $this->item($me, ['name' => 'CHIPSY PAPRIKA', 'total_cents' => 10000]);
        $this->item($me, ['name' => 'CHIPSY PAPRIKA', 'total_cents' => 10000]);
        $this->item($sibling, ['name' => 'THEIR SNACK']);

        RaiStatsServer::actingAs($this->actingUser($me, TokenScope::Self))
            ->tool(ListUncategorizedReceiptItemsTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('total_uncategorized_items', 2)
                ->where('distinct_uncategorized_names', 1)
                ->where('returned_names', 1)
                ->where('names.0.name', 'CHIPSY PAPRIKA')
                ->where('names.0.times_bought', 2)
                ->where('names.0.spend_cents', 20000)
            );
    }

    public function test_min_occurrences_filters_rare_names(): void
    {
        $me = User::factory()->create();
        $this->item($me, ['name' => 'RARE ITEM']);
        $this->item($me, ['name' => 'COMMON ITEM']);
        $this->item($me, ['name' => 'COMMON ITEM']);

        $response = RaiStatsServer::actingAs($this->actingUser($me, TokenScope::Self))
            ->tool(ListUncategorizedReceiptItemsTool::class, ['min_occurrences' => 2]);

        $response->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('returned_names', 1)
            ->where('names.0.name', 'COMMON ITEM')
            ->etc()
        );
    }

    public function test_list_receipt_items_filters_by_name_and_category_source(): void
    {
        $me = User::factory()->create();
        $category = ProductCategory::factory()->create();
        $this->item($me, ['name' => 'MLEKO 2.8%', 'product_category_id' => $category->id, 'category_source' => CategorySource::Rule]);
        $this->item($me, ['name' => 'HLEB SOMUN']);

        RaiStatsServer::actingAs($this->actingUser($me, TokenScope::Self))
            ->tool(ListReceiptItemsTool::class, ['name' => 'mleko'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('items', 1)
                ->where('items.0.name', 'MLEKO 2.8%')
                ->where('items.0.category_source', 'rule')
                ->where('items.0.provider', ReceiptProvider::Maxi->value)
            );
    }

    public function test_family_token_sees_every_accounts_items(): void
    {
        $me = User::factory()->create();
        $sibling = User::factory()->create();
        $this->item($me, ['name' => 'A']);
        $this->item($sibling, ['name' => 'B']);

        RaiStatsServer::actingAs($this->actingUser($me, TokenScope::Family))
            ->tool(ListUncategorizedReceiptItemsTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('total_uncategorized_items', 2)
                ->etc()
            );
    }
}
