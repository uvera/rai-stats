<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\GroceryStats;
use App\Filament\Resources\ArticlePrices\ArticlePriceResource;
use App\Filament\Resources\ArticlePrices\Pages\ListArticlePrices;
use App\Filament\Resources\ArticlePrices\Pages\ViewArticlePrice;
use App\Filament\Resources\GroceryAccounts\Pages\CreateGroceryAccount;
use App\Filament\Resources\GroceryAccounts\Pages\ListGroceryAccounts;
use App\Filament\Resources\GroceryReceipts\Pages\ListGroceryReceipts;
use App\Filament\Resources\GroceryReceipts\Pages\ViewGroceryReceipt;
use App\Filament\Resources\GroceryReceipts\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\RelationManagers\ArticlesRelationManager;
use App\Filament\Widgets\Groceries\ArticlePriceHistoryChart;
use App\Filament\Widgets\Groceries\ArticlePurchasesTable;
use App\Filament\Widgets\Groceries\BasketSizeOverTimeChart;
use App\Filament\Widgets\Groceries\GroceryStatsOverview;
use App\Filament\Widgets\Groceries\ProductCategorySpendChart;
use App\Filament\Widgets\Groceries\TopProductsChart;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\GroceryReceiptItem;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroceriesSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_can_view_the_moj_maxi_pages(): void
    {
        $this->actingAs(User::factory()->create());
        GroceryAccount::factory()->create();
        GroceryReceipt::factory()->create();

        Livewire::test(ListGroceryAccounts::class)->assertOk();
        Livewire::test(ListGroceryReceipts::class)->assertOk();
        Livewire::test(GroceryStats::class)->assertOk();
    }

    public function test_every_grocery_stats_widget_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $account = GroceryAccount::factory()->create();
        $receipt = GroceryReceipt::factory()->for($account, 'account')->create([
            'purchased_at' => now(), 'total_cents' => 40000,
        ]);
        GroceryReceiptItem::factory()->for($receipt, 'receipt')->create([
            'name' => 'Milk', 'total_cents' => 12000, 'vat_rate' => 20,
        ]);

        $filters = ['pageFilters' => [
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]];

        foreach ([
            GroceryStatsOverview::class,
            BasketSizeOverTimeChart::class,
            ProductCategorySpendChart::class,
            TopProductsChart::class,
        ] as $widget) {
            Livewire::test($widget, $filters)->assertOk();
        }
    }

    public function test_non_admin_cannot_create_a_maxi_account(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateGroceryAccount::class)->assertForbidden();
    }

    public function test_admin_can_create_a_maxi_account_and_a_device_uuid_is_generated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateGroceryAccount::class)
            ->set('data.provider', 'maxi')
            ->set('data.label', 'Household Maxi')
            ->set('data.email', 'household@example.com')
            ->set('data.password', 'secret')
            ->call('create')
            ->assertHasNoFormErrors();

        $account = GroceryAccount::firstWhere('email', 'household@example.com');
        $this->assertNotNull($account);
        $this->assertNotEmpty($account->device_uuid);
    }

    public function test_link_action_is_admin_only_on_the_receipt_view(): void
    {
        $receipt = GroceryReceipt::factory()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ViewGroceryReceipt::class, ['record' => $receipt->getKey()])
            ->assertActionHidden('linkTransaction');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewGroceryReceipt::class, ['record' => $receipt->getKey()])
            ->assertActionVisible('linkTransaction');
    }

    public function test_article_prices_list_searches_and_the_view_shows_history(): void
    {
        $this->actingAs(User::factory()->create());

        $older = GroceryReceipt::factory()->create([
            'purchased_at' => '2026-03-01 10:00:00',
            'store_name' => 'Maxi 12',
        ]);
        $newer = GroceryReceipt::factory()->create([
            'purchased_at' => '2026-06-01 10:00:00',
            'store_name' => 'Maxi 40',
        ]);
        $metro = GroceryReceipt::factory()->metro()->create([
            'purchased_at' => '2026-08-01 10:00:00',
            'store_name' => 'Metro 22',
        ]);

        $firstMilk = GroceryReceiptItem::factory()->for($older, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Milk',
            'unit_price_cents' => 10000,
            'quantity' => 1,
            'total_cents' => 10000,
        ]);
        $laterMilk = GroceryReceiptItem::factory()->for($newer, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Milk',
            'unit_price_cents' => 15000,
            'quantity' => 1,
            'total_cents' => 15000,
        ]);
        $bread = GroceryReceiptItem::factory()->for($older, 'receipt')->create([
            'line_no' => 2,
            'name' => 'Bread',
            'unit_price_cents' => 8000,
            'quantity' => 1,
            'total_cents' => 8000,
        ]);
        $lime = GroceryReceiptItem::factory()->for($metro, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Lime',
            'unit_price_cents' => 5000,
            'quantity' => 1,
            'total_cents' => 5000,
        ]);

        Livewire::test(ListArticlePrices::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$firstMilk, $bread, $lime])
            ->searchTable('Milk')
            ->assertCanSeeTableRecords([$firstMilk])
            ->assertCanNotSeeTableRecords([$bread, $lime]);

        Livewire::test(ListArticlePrices::class)
            ->filterTable('provider', 'metro')
            ->assertCanSeeTableRecords([$lime])
            ->assertCanNotSeeTableRecords([$firstMilk, $bread]);

        Livewire::test(ViewArticlePrice::class, ['record' => $firstMilk->getKey()])
            ->assertOk()
            ->assertSee('Milk')
            ->assertSee('150.00')
            ->assertSee('100.00')
            ->assertSee('+50.0%')
            ->assertSee('Since cheapest')
            ->assertSee('Maxi 40')
            ->assertSee('Unit price over time');

        Livewire::test(ArticlePurchasesTable::class, ['record' => $firstMilk])
            ->assertOk()
            ->assertCanSeeTableRecords([$laterMilk, $firstMilk], inOrder: true)
            ->assertSee('Maxi 40')
            ->assertSee('150.00');

        Livewire::test(ArticlePriceHistoryChart::class, ['record' => $firstMilk])
            ->assertOk()
            ->assertSee('Unit price over time')
            ->assertSee('100')
            ->assertSee('150');
    }

    public function test_article_purchase_history_sorts_by_unit_price(): void
    {
        $this->actingAs(User::factory()->create());

        $older = GroceryReceipt::factory()->create(['purchased_at' => '2026-03-01 10:00:00']);
        $newer = GroceryReceipt::factory()->create(['purchased_at' => '2026-06-01 10:00:00']);

        $cheaper = GroceryReceiptItem::factory()->for($older, 'receipt')->create([
            'name' => 'Milk',
            'unit_price_cents' => 10000,
            'total_cents' => 10000,
        ]);
        $pricier = GroceryReceiptItem::factory()->for($newer, 'receipt')->create([
            'name' => 'Milk',
            'unit_price_cents' => 15000,
            'total_cents' => 15000,
        ]);

        Livewire::test(ArticlePurchasesTable::class, ['record' => $cheaper])
            ->assertCanSeeTableRecords([$pricier, $cheaper], inOrder: true)
            ->sortTable('unit_price_cents')
            ->assertCanSeeTableRecords([$cheaper, $pricier], inOrder: true);
    }

    public function test_receipt_items_link_to_article_price_history(): void
    {
        $this->actingAs(User::factory()->create());

        $receipt = GroceryReceipt::factory()->create();
        $milk = GroceryReceiptItem::factory()->for($receipt, 'receipt')->create([
            'line_no' => 1,
            'name' => 'Milk',
        ]);
        $bread = GroceryReceiptItem::factory()->for($receipt, 'receipt')->create([
            'line_no' => 2,
            'name' => 'Bread',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $receipt,
            'pageClass' => ViewGroceryReceipt::class,
        ])
            ->assertCanSeeTableRecords([$milk, $bread])
            ->assertSee(ArticlePriceResource::getUrl('view', ['record' => $milk]), false)
            ->assertSee(ArticlePriceResource::getUrl('view', ['record' => $bread]), false);
    }

    public function test_category_articles_link_to_price_history_once_per_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $dairy = ProductCategory::factory()->create(['name' => 'Dairy']);
        $produce = ProductCategory::factory()->create(['name' => 'Produce']);

        $older = GroceryReceipt::factory()->create(['purchased_at' => '2026-03-01 10:00:00']);
        $newer = GroceryReceipt::factory()->create(['purchased_at' => '2026-06-01 10:00:00']);

        $firstMilk = GroceryReceiptItem::factory()->for($older, 'receipt')->create([
            'name' => 'Milk',
            'product_category_id' => $dairy->id,
        ]);
        $laterMilk = GroceryReceiptItem::factory()->for($newer, 'receipt')->create([
            'name' => 'Milk',
            'product_category_id' => $dairy->id,
        ]);
        $apple = GroceryReceiptItem::factory()->for($newer, 'receipt')->create([
            'name' => 'Apple',
            'product_category_id' => $produce->id,
        ]);

        Livewire::test(EditProductCategory::class, ['record' => $dairy->getKey()])
            ->assertOk()
            ->assertSee('Articles');

        Livewire::test(ArticlesRelationManager::class, [
            'ownerRecord' => $dairy,
            'pageClass' => EditProductCategory::class,
        ])
            ->assertCanSeeTableRecords([$firstMilk])
            ->assertCanNotSeeTableRecords([$laterMilk, $apple])
            ->assertSee('Milk')
            ->assertSee('2')
            ->assertDontSee('Apple')
            ->assertSee(ArticlePriceResource::getUrl('view', ['record' => $firstMilk]), false)
            ->assertDontSee(ArticlePriceResource::getUrl('view', ['record' => $laterMilk]), false)
            ->searchTable('Milk')
            ->assertCanSeeTableRecords([$firstMilk])
            ->sortTable('times_bought');
    }

    public function test_sync_actions_are_admin_only_on_the_accounts_table(): void
    {
        $account = GroceryAccount::factory()->withValidToken()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ListGroceryAccounts::class)
            ->assertTableActionHidden('sync', $account);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListGroceryAccounts::class)
            ->assertTableActionVisible('sync', $account);
    }
}
