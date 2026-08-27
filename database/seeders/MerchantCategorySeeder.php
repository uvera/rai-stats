<?php

namespace Database\Seeders;

use App\Support\MerchantCategoryData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds categories/rules from the bundled JSON fixture (see
 * app/Support/MerchantCategoryData.php for the import logic). To update the
 * fixture after editing rules via the Categories admin resource, run
 * `php artisan merchant-categories:export --path=database/seeders/data/merchant-categories.json`
 * and commit the result.
 */
class MerchantCategorySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/merchant-categories.json');
        $data = json_decode(File::get($path), true);

        app(MerchantCategoryData::class)->import($data);
    }
}
