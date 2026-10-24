<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Models\Category;
use App\Models\MerchantCategoryRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_categories_action_streams_a_json_download(): void
    {
        $admin = User::factory()->create();
        Category::factory()->create(['name' => 'Groceries']);

        $this->actingAs($admin);

        Livewire::test(ListCategories::class)
            ->callAction('exportCategories')
            ->assertFileDownloaded('merchant-categories.json');
    }

    public function test_import_categories_action_imports_uploaded_json(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $path = sys_get_temp_dir().'/categories-page-test-import.json';
        file_put_contents($path, json_encode([
            'categories' => [
                ['name' => 'Groceries', 'color' => null, 'rules' => [['pattern' => 'MAXI']]],
            ],
        ]));

        Livewire::test(ListCategories::class)
            ->callAction('importCategories', data: [
                'file' => UploadedFile::fake()->createWithContent('categories.json', file_get_contents($path)),
            ]);

        unlink($path);

        $this->assertDatabaseHas('categories', ['name' => 'Groceries']);
        $this->assertDatabaseHas('merchant_category_rules', ['pattern' => 'MAXI']);
    }

    public function test_recategorize_action_updates_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI']);

        $this->actingAs($admin);

        Livewire::test(ListCategories::class)
            ->callAction('recategorize')
            ->assertOk();
    }

    public function test_only_admins_may_change_merchant_categories(): void
    {
        $category = Category::factory()->create();
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($member);
        $this->assertTrue(CategoryResource::canViewAny());
        $this->assertFalse(CategoryResource::canCreate());
        $this->assertFalse(CategoryResource::canEdit($category));

        Livewire::test(ListCategories::class)
            ->assertActionHidden('create')
            ->assertActionHidden('recategorize')
            ->assertActionHidden('importCategories')
            ->assertActionVisible('exportCategories');

        $this->actingAs($admin);
        $this->assertTrue(CategoryResource::canCreate());
        $this->assertTrue(CategoryResource::canEdit($category));

        Livewire::test(ListCategories::class)
            ->assertActionVisible('create')
            ->assertActionVisible('recategorize')
            ->assertActionVisible('importCategories');
    }

    public function test_only_admins_may_recategorize_product_items(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListProductCategories::class)
            ->assertActionHidden('recategorizeItems');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListProductCategories::class)
            ->assertActionVisible('recategorizeItems');
    }
}
