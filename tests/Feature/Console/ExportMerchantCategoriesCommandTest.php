<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\MerchantCategoryRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportMerchantCategoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_current_categories_and_rules_as_json_to_stdout(): void
    {
        $category = Category::factory()->create(['name' => 'Groceries', 'color' => '#00ff00']);
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI', 'priority' => 5]);

        Artisan::call('merchant-categories:export');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame('Groceries', $decoded['categories'][0]['name']);
        $this->assertSame('#00ff00', $decoded['categories'][0]['color']);
        $this->assertSame('MAXI', $decoded['categories'][0]['rules'][0]['pattern']);
        $this->assertSame(5, $decoded['categories'][0]['rules'][0]['priority']);
    }

    public function test_exports_to_a_file_when_path_is_given(): void
    {
        Category::factory()->create(['name' => 'Groceries']);
        $path = sys_get_temp_dir().'/merchant-categories-export-test.json';

        Artisan::call('merchant-categories:export', ['--path' => $path]);

        $this->assertTrue(File::exists($path));
        $decoded = json_decode(File::get($path), true);
        $this->assertSame('Groceries', $decoded['categories'][0]['name']);

        File::delete($path);
    }
}
