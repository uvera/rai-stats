<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\ListCategories;
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
        $admin = User::factory()->create();
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
        $admin = User::factory()->create();
        $category = Category::factory()->create();
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI']);

        $this->actingAs($admin);

        Livewire::test(ListCategories::class)
            ->callAction('recategorize')
            ->assertOk();
    }
}
