<?php

namespace Tests\Feature\Filament;

use App\Enums\PromotionSource;
use App\Enums\SyncRunStatus;
use App\Filament\Pages\Promotions;
use App\Filament\Resources\Promotions\Pages\ListAllPromotions;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Jobs\RefreshMaxiPromotions;
use App\Jobs\RefreshMetroPromotions;
use App\Models\GroceryAccount;
use App\Models\GroceryPromotion;
use App\Models\GroceryPromotionMatch;
use App\Models\SyncRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_sees_matches_and_cannot_refresh(): void
    {
        $this->actingAs(User::factory()->create());

        $account = GroceryAccount::factory()->metro()->create(['label' => 'Home Metro']);
        $other = GroceryAccount::factory()->metro()->create(['label' => 'Other Metro']);

        $cheese = GroceryPromotion::factory()->for($account, 'account')->create([
            'name' => 'COLLA Tvrdi sir parmigano reggiano 200 g',
            'gross_cents' => 45599,
            'strike_gross_cents' => 59999,
            'image_url' => 'https://cdn.metro-group.com/rs/cheese.png',
            'ends_on' => '2026-10-28',
        ]);
        $cheeseMatch = GroceryPromotionMatch::factory()->for($cheese, 'promotion')->create([
            'article_name' => 'COLLA PARMIGANO REGGIANO 200G',
            'times_bought' => 4,
            'last_unit_price_cents' => 48000,
        ]);

        $breadPromo = GroceryPromotion::factory()->for($other, 'account')->create([
            'name' => 'Hleb beli 500 g',
            'gross_cents' => 19000,
            'strike_gross_cents' => 20000,
        ]);
        $breadMatch = GroceryPromotionMatch::factory()->for($breadPromo, 'promotion')->create([
            'article_name' => 'HLEB',
            'times_bought' => 9,
            'last_unit_price_cents' => 20000,
        ]);

        Livewire::test(ListPromotions::class)
            ->assertOk()
            ->assertActionHidden('refresh')
            ->assertCanSeeTableRecords([$cheeseMatch, $breadMatch], inOrder: true)
            ->assertSee('COLLA Tvrdi sir')
            ->assertSee('COLLA PARMIGANO REGGIANO 200G')
            ->assertSee('455.99')
            ->assertSee('599.99')
            ->assertSee('480.00')
            ->assertSee('24.0%')
            ->searchTable('200G')
            ->assertCanSeeTableRecords([$cheeseMatch])
            ->assertCanNotSeeTableRecords([$breadMatch]);

        Livewire::test(ListPromotions::class)
            ->filterTable('account', $other->id)
            ->assertCanSeeTableRecords([$breadMatch])
            ->assertCanNotSeeTableRecords([$cheeseMatch]);

        Livewire::test(ListPromotions::class)
            ->sortTable('article_name', 'desc')
            ->assertCanSeeTableRecords([$breadMatch, $cheeseMatch], inOrder: true)
            ->sortTable('promotion.gross_cents')
            ->assertCanSeeTableRecords([$breadMatch, $cheeseMatch], inOrder: true);

        Livewire::test(ListAllPromotions::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$cheese, $breadPromo])
            ->searchTable('Hleb')
            ->assertCanSeeTableRecords([$breadPromo])
            ->assertCanNotSeeTableRecords([$cheese]);

        Livewire::test(ListAllPromotions::class)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$breadPromo, $cheese], inOrder: true)
            ->sortTable('gross_cents')
            ->assertCanSeeTableRecords([$breadPromo, $cheese], inOrder: true)
            ->sortTable('discount')
            ->assertCanSeeTableRecords([$breadPromo, $cheese], inOrder: true);
    }

    public function test_an_admin_can_queue_a_refresh(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());
        GroceryAccount::factory()->metro()->withValidToken()->create();

        Livewire::test(ListAllPromotions::class)
            ->assertActionVisible('refresh')
            ->callAction('refresh')
            ->assertNotified('Refresh started for 1 account');

        Queue::assertPushed(RefreshMetroPromotions::class, 1);
        Queue::assertNotPushed(RefreshMaxiPromotions::class);
        $run = SyncRun::query()->first();
        $this->assertNotNull($run);
        $this->assertSame(SyncRunStatus::Running, $run->status);
    }

    public function test_promotions_are_nested_under_groceries(): void
    {
        $this->actingAs(User::factory()->create());

        $group = collect(Filament::getNavigation())
            ->first(fn ($group) => $group->getLabel() === 'Groceries');

        $this->assertNotNull($group);

        $promotions = collect($group->getItems())
            ->first(fn ($item) => $item->getLabel() === 'Promotions');

        $this->assertNotNull($promotions);
        $this->assertSame(
            ['All Promotions', 'Matching Promotions'],
            collect($promotions->getChildItems())->map(fn ($item) => $item->getLabel())->all(),
        );

        Livewire::test(Promotions::class)
            ->assertRedirect(ListAllPromotions::getUrl());
    }

    public function test_an_admin_can_queue_a_maxi_refresh(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());
        GroceryAccount::factory()->withValidToken()->create();

        Livewire::test(ListAllPromotions::class)
            ->assertActionVisible('refresh')
            ->callAction('refresh')
            ->assertNotified('Refresh started for 1 account');

        Queue::assertPushed(RefreshMaxiPromotions::class, 1);
        Queue::assertNotPushed(RefreshMetroPromotions::class);
    }

    public function test_personal_promotions_can_be_filtered_against_triweekly(): void
    {
        $this->actingAs(User::factory()->create());
        $account = GroceryAccount::factory()->create(['label' => 'Home Maxi']);

        $catalog = GroceryPromotion::factory()->for($account, 'account')->create([
            'source' => PromotionSource::Triweekly,
            'shop_variant_id' => '111',
            'name' => 'Catalog almonds',
            'gross_cents' => 71399,
        ]);
        $cheaper = GroceryPromotion::factory()->for($account, 'account')->create([
            'source' => PromotionSource::Directed,
            'shop_variant_id' => '111',
            'name' => 'Personal almonds',
            'gross_cents' => 67800,
        ]);
        $dearCatalog = GroceryPromotion::factory()->for($account, 'account')->create([
            'source' => PromotionSource::Triweekly,
            'shop_variant_id' => '222',
            'name' => 'Catalog honey',
            'gross_cents' => 15000,
        ]);
        $dearer = GroceryPromotion::factory()->for($account, 'account')->create([
            'source' => PromotionSource::Directed,
            'shop_variant_id' => '222',
            'name' => 'Personal honey',
            'gross_cents' => 20000,
        ]);
        $onlyPersonal = GroceryPromotion::factory()->for($account, 'account')->create([
            'source' => PromotionSource::Directed,
            'shop_variant_id' => '333',
            'name' => 'Personal only',
            'gross_cents' => 10000,
        ]);

        Livewire::test(ListAllPromotions::class)
            ->filterTable('personal_vs_triweekly', 'cheaper')
            ->assertCanSeeTableRecords([$cheaper])
            ->assertCanNotSeeTableRecords([$catalog, $dearer, $dearCatalog, $onlyPersonal])
            ->assertSee('713.99')
            ->assertSee('-35.99');

        Livewire::test(ListAllPromotions::class)
            ->filterTable('personal_vs_triweekly', 'expensive')
            ->assertCanSeeTableRecords([$dearer])
            ->assertCanNotSeeTableRecords([$cheaper, $catalog, $onlyPersonal])
            ->assertSee('150.00')
            ->assertSee('50.00');

        $cheaperMatch = GroceryPromotionMatch::factory()->for($cheaper, 'promotion')->create([
            'article_name' => 'BADEM',
        ]);
        $catalogMatch = GroceryPromotionMatch::factory()->for($catalog, 'promotion')->create([
            'article_name' => 'BADEM KATALOG',
        ]);

        Livewire::test(ListPromotions::class)
            ->filterTable('personal_vs_triweekly', 'cheaper')
            ->assertCanSeeTableRecords([$cheaperMatch])
            ->assertCanNotSeeTableRecords([$catalogMatch])
            ->assertSee('713.99')
            ->assertSee('-35.99');
    }
}
