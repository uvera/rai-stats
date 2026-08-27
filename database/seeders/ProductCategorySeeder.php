<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * A starter product-category taxonomy for the Moj Maxi section, with a few
 * obvious keyword rules to get auto-categorisation going. Refine the rules
 * in the Product categories admin resource, then run
 * `php artisan maxi:recategorize-items`.
 */
class ProductCategorySeeder extends Seeder
{
    /**
     * @var array<string, array{color: string, rules: array<int, string>}>
     */
    private const TAXONOMY = [
        'Mlečni proizvodi' => ['color' => '#3b82f6', 'rules' => ['mleko', 'jogurt', 'sir', 'pavlaka', 'maslac', 'kajmak']],
        'Voće i povrće' => ['color' => '#22c55e', 'rules' => ['banana', 'jabuka', 'paprika', 'paradajz', 'krastavac', 'ananas', 'borovnica', 'limun', 'krompir', 'luk']],
        'Meso i mesne prerađevine' => ['color' => '#ef4444', 'rules' => ['salama', 'šunka', 'sunka', 'kobasica', 'pileć', 'pilec', 'junet', 'svinj', 'slanina', 'viršle', 'virsle']],
        'Hleb i pekara' => ['color' => '#f59e0b', 'rules' => ['hleb', 'pecivo', 'kifla', 'peciv', 'baget', 'brioš', 'brios']],
        'Pića' => ['color' => '#06b6d4', 'rules' => ['voda', 'sok', 'coca', 'cola', 'pivo', 'vino', 'kafa', 'čaj', 'caj']],
        'Grickalice i slatkiši' => ['color' => '#ec4899', 'rules' => ['čokolad', 'cokolad', 'keks', 'čips', 'cips', 'bombon', 'napolitank', 'štangl', 'stangl']],
        'Osnovne namirnice' => ['color' => '#a855f7', 'rules' => ['brašno', 'brasno', 'šećer', 'secer', 'so ', 'ulje', 'pirinač', 'pirinac', 'testenin', 'pasulj']],
        'Higijena i domaćinstvo' => ['color' => '#64748b', 'rules' => ['deterdžent', 'deterdzent', 'sapun', 'šampon', 'sampon', 'toalet', 'ubrus', 'kesa', 'sredstvo']],
    ];

    public function run(): void
    {
        foreach (self::TAXONOMY as $name => $definition) {
            $category = ProductCategory::updateOrCreate(
                ['name' => $name],
                ['color' => $definition['color']],
            );

            foreach ($definition['rules'] as $pattern) {
                $category->rules()->updateOrCreate(['pattern' => $pattern], ['priority' => 0]);
            }
        }
    }
}
