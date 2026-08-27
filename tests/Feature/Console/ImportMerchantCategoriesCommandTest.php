<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\MerchantCategoryRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportMerchantCategoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_categories_and_rules_from_a_json_file(): void
    {
        $path = sys_get_temp_dir().'/merchant-categories-import-test.json';
        File::put($path, json_encode([
            'categories' => [
                [
                    'name' => 'Groceries',
                    'color' => '#00ff00',
                    'rules' => [
                        ['pattern' => 'MAXI', 'priority' => 5],
                    ],
                ],
            ],
        ]));

        Artisan::call('merchant-categories:import', ['path' => $path]);

        $this->assertDatabaseHas('categories', ['name' => 'Groceries', 'color' => '#00ff00']);
        $this->assertDatabaseHas('merchant_category_rules', ['pattern' => 'MAXI', 'priority' => 5]);

        File::delete($path);
    }

    public function test_import_is_additive_and_updates_existing_categories_by_name(): void
    {
        $category = Category::factory()->create(['name' => 'Groceries', 'color' => null]);
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'EXISTING']);

        $path = sys_get_temp_dir().'/merchant-categories-import-test-2.json';
        File::put($path, json_encode([
            'categories' => [
                ['name' => 'Groceries', 'color' => '#00ff00', 'rules' => [['pattern' => 'MAXI']]],
            ],
        ]));

        Artisan::call('merchant-categories:import', ['path' => $path]);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('categories', ['name' => 'Groceries', 'color' => '#00ff00']);
        $this->assertDatabaseHas('merchant_category_rules', ['pattern' => 'EXISTING']);
        $this->assertDatabaseHas('merchant_category_rules', ['pattern' => 'MAXI']);

        File::delete($path);
    }

    public function test_fails_gracefully_when_the_file_does_not_exist(): void
    {
        $exitCode = Artisan::call('merchant-categories:import', ['path' => '/nonexistent/path.json']);

        $this->assertSame(1, $exitCode);
    }
}
