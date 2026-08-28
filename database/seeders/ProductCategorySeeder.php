<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * A starter product-category taxonomy for the grocery section, with keyword
 * rules to drive auto-categorisation. Rules are case-insensitive substring
 * matches; the highest `priority` wins, so specific categories (fish, pet
 * food, non-food) outrank the broad ones. Refine the rules in the Product
 * categories admin resource, then run:
 *
 *   php artisan db:seed --class=ProductCategorySeeder
 *   php artisan groceries:recategorize-items
 *
 * Patterns are inferred from Serbian fiscal-receipt item names (Maxi/Metro),
 * which are abbreviated and inconsistently accented - hence the paired
 * accented / unaccented variants.
 */
class ProductCategorySeeder extends Seeder
{
    /**
     * @var array<string, array{color: string, priority?: int, rules: array<int, string>}>
     */
    private const TAXONOMY = [
        'Kućni ljubimci' => ['color' => '#b45309', 'priority' => 15, 'rules' => [
            'hrana pas', 'hrana za pse', 'hrana za mačke', 'hrana mačk', ' za pse', 'psi vel', 'psi sred',
            'pedigree', 'whiskas', 'friskies', 'felix', 'chappi', 'purina', 'brekkies',
            'poslastica za pse', 'dent.pos', 'dent. pos', 'akvarijum', 'grumus',
        ]],
        'Riba i morski plodovi' => ['color' => '#0ea5e9', 'priority' => 12, 'rules' => [
            'losos', 'orada', 'brancin', 'oslić', 'oslic', 'skuša', 'skusa', 'sardin', 'papalin',
            'pastrmk', 'šaran', 'saran', 'smuđ', 'smudj', 'bakalar', 'haringa', 'inćun', 'incun',
            'tuna', 'tunj', 'tun.', 'rio mare', 'riblj', 'morski plod', 'plodovi mora',
            'lignj', 'dagnj', 'škamp', 'skamp', 'kavijar', 'file lososa',
        ]],
        'Alkoholna pića' => ['color' => '#7c3aed', 'priority' => 12, 'rules' => [
            'pivo', 'vino', 'radler', 'viski', 'whisky', 'whiskey', 'bourbon', 'jameson', 'jack daniel',
            ' rum ', 'rum white', 'votka', 'vodka', 'jager', 'jägermeister', 'jagermeister',
            'liker', 'rakij', 'konjak', 'cognac', 'vinjak', 'brendi', 'brandy',
            'tekila', 'tequila', 'aperol', 'campari', 'vermut', 'martini', 'prosecco',
            'šampanjac', 'sampanjac', 'pelinkovac', 'kruškovac', 'travarica', 'lozovač', 'lozovac',
            'gorki list', 'heineken', 'tuborg', 'staropramen', 'niksicko', 'nikšić',
            'aurel', 'tamjanika', 'chardonnay', 'merlot', 'cabernet', 'vranac', 'bariq',
        ]],
        'Zamrznuta hrana' => ['color' => '#38bdf8', 'priority' => 10, 'rules' => [
            'frikom', 'smrznut', 'smrz', 'zamrznut', 'zamrz', 'ledeni', 'pomfrit',
            'njoke', 'njoki', 'lisnato testo smrz',
        ]],
        'Jaja' => ['color' => '#facc15', 'priority' => 10, 'rules' => [
            'jaja', 'jaje', 'prepeličj', 'prepelicj',
        ]],
        'Higijena i domaćinstvo' => ['color' => '#64748b', 'priority' => 8, 'rules' => [
            'deterdžent', 'deterdzent', 'sapun', 'šampon', 'sampon', 'toalet', 'ubrus',
            'kese ', 'kesa ', 'kesa-', 'kese za', 'kesa za', 'kese smece', 'kese  smece', 'vinil np rukavice',
            'sredstvo', 'perfex', 'fino ', 'vileda', 'frosch', 'perwoll', 'coccolino', 'tesori',
            'mr. proper', 'mr.proper', 'mr proper', 'domestos', 'duck', 'omekšivač', 'omeksivac',
            'maramic', 'sunđer', 'sundjer', 'krpa', 'folija', 'rukavic', 'papir za pec',
            'asepsol', 'wc ', 'vlazne', 'vlažne', 'pelene', 'ulošci', 'ulosci', 'tuferi', 'vata za',
            'čačkalic', 'cackalic', 'salvet', 'osveživač', 'osvezivac', 'đubre', 'djubre',
            'smeće', 'smece', 'higijen', 'čistač', 'cistac', 'wettex', 'kesa za', 'mop ',
            't. pap', 't.pap', 'inox jast', 'jastuč', 'jastuc', 'merix', 'svežina', 'svezina',
        ]],
        'Neprehrambeno i kućni pribor' => ['color' => '#78716c', 'priority' => 8, 'rules' => [
            'serpa', 'šerpa', 'tiganj', 'pleh', 'pleg', 'kanta', 'roštilj', 'rostilj', 'mlin za',
            'cedilj', 'kutlač', 'kutlac', 'tanjir', 'bokal', 'ćinij', 'cinij', 'činij', 'mutilic',
            'hvataljk', 'šroper', 'sroper', 'četka', 'cetka', 'karcher', 'filter kese', 'lepak',
            'ljepak', 'saksij', 'dracena', 'auto staza', 'posuda', 'poklopac', 'rende', 'avan ',
            'sijalic', 'baterij', 'produžni', 'produzni', 'sveća', 'sveca', 'upaljač', 'upaljac',
            'šibice', 'sibice', 'punjač', 'punjac', 'kabl', 'termofor', 'kutija provid',
            ' jast', 'teflon', 'kendo', 'fackelmann',
        ]],
        'Meso i mesne prerađevine' => ['color' => '#ef4444', 'priority' => 5, 'rules' => [
            'salama', 'šunka', 'sunka', 'kobasica', ' kob', 'pileć', 'pilec', 'junet', 'junec',
            'svinj', 'slanina', 'viršle', 'virsle', 'prsut', 'pršut', 'pečenic', 'pecenic', 'pecet',
            'kulen', 'čajna', 'cajna', 'pančet', 'pancet', 'dimljen', 'biber blok', 'ćevap', 'cevap',
            'pljeskavic', 'mleveno meso', 'mleven', 'batak', 'krilca', 'rebra', 'file mesa',
            'simental', 'burger map', 'ćuret', 'curet', 'gurmanska', 'čvarci', 'cvarci',
        ]],
        'Sosovi, konzerve i namazi' => ['color' => '#d97706', 'priority' => 5, 'rules' => [
            'kečap', 'kecap', 'ketchup', 'majonez', 'senf', 'ajvar', 'pašteta', 'pasteta', 'pesto',
            'argeta', 'sos ', ' sos', 'preliv', 'dresing', 'humus', 'tahini', 'konzerv',
            'pasirani paradajz', 'pelat', 'masline', 'kapar', 'turšij', 'tursij', 'krem za kuvanje',
            'sojin sos', 'čili sos', 'barbecue',
        ]],
        'Grickalice i slatkiši' => ['color' => '#ec4899', 'priority' => 5, 'rules' => [
            'čokolad', 'cokolad', 'čoko', 'coko', 'cok.', 'keks', 'čips', 'cips', 'bombon',
            'napolitank', 'štangl', 'stangl', 'ferrero', 'milka', 'plazma', '7 days', '7days',
            'smoki', 'flips', 'grisin', 'štapić', 'stapic', 'gelato', 'sladoled', 'slad.', 'dessert',
            'zottis', 'wafer', 'krekeri', 'indijski orah', 'orah slani', 'kikiriki', 'badem',
            'lešnik', 'lesnik', 'menthol to go', 'žvake', 'zvake', 'nutella', 'eurocrem',
            'bananica', 'jaffa', 'domaćic', 'domacic', 'pizza puz', 'puz 68',
        ]],
        'Hleb i pekara' => ['color' => '#f59e0b', 'priority' => 3, 'rules' => [
            'hleb', 'pecivo', 'kifla', 'peciv', 'baget', 'brioš', 'brios', 'tortilj', 'tortill',
            'kore za', 'dvopek', 'lepinj', 'pinsa', 'pica pod', 'pizza pod', 'giban', 'štrudl',
            'strudl', 'perec', 'krofn', 'somun', 'pogačic', 'pogacica', 'burek', 'proja',
        ]],
        'Mlečni proizvodi' => ['color' => '#3b82f6', 'priority' => 3, 'rules' => [
            'mleko', 'jogurt', 'jog.', 'sir', 'pavlaka', 'pavl', 'pav.', 'maslac', 'kajmak',
            'mileram', 'mozzarel', 'mozarel', 'gauda', 'gouda', 'kačkavalj', 'kaskaval', 'feta',
            'edamer', 'zdenka', 'alpro', 'bio barista', 'barista mleko', 'barista soya', 'milk drink',
            'somborsk', 'balans', 'sojino mleko', 'bademovo mleko', 'ovseno mleko', 'biljni napitak',
        ]],
        'Voće i povrće' => ['color' => '#22c55e', 'rules' => [
            'banana', 'jabuka', 'paprika', 'paradajz', 'krastavac', 'ananas', 'borovnica', 'limun',
            'krompir', 'luk', 'avokado', 'tikvic', 'šargarep', 'sargarep', 'lubenic', 'batat',
            'urme', 'pečurk', 'pecurk', 'šampinjon', 'sampinjon', 'kupus', 'spanać', 'spanac',
            'brokoli', 'karfiol', 'rukola', 'salata', 'šljiv', 'sljiv', 'malina', 'kupina',
            'mrkva', 'cvekla', 'blitva', 'praziluk', 'limeta', 'grejpfrut', 'pomorandza', 'pomorandž',
        ]],
        'Pića' => ['color' => '#06b6d4', 'priority' => 2, 'rules' => [
            'voda', 'sok', 'coca', 'cola', 'kafa', 'kafe', 'nescafe', 'cappuccino',
            'kapućino', 'espresso', 'čaj', 'caj', 'schweppes',
            'fanta', 'sprite', 'next', 'fuzetea', 'fuze tea', 'aqua viva', 'nectar', 'nektar',
            'cedevita', 'knjaz miloš', 'knjaz milos', 'limenka', 'guarana', 'energetsk', 'red bull',
            'monster', 'smoothie', 'voćne kapi', 'vocne kapi', 'voće&', 'voce&', 'gazir', 'mineralna',
            'pepsi', 'knjaz gaz', 'ledeni čaj', 'ledeni caj', 'prolom voda', 'mivela',
        ]],
        'Osnovne namirnice' => ['color' => '#a855f7', 'rules' => [
            'brašno', 'brasno', 'šećer', 'secer', 'sitna so', 'krupna so', 'morska so', 'kuhinjska so',
            'jodirana', 'himalajsk', 'ulje', 'pirinač', 'pirinac', 'testenin',
            'pasulj', 'vegeta', 'začin', 'zacin', 'kotanyi', 'aleva', 'supa', 'griz', 'kvasac',
            'susam', 'cimet', 'vanilin', 'prašak za pec', 'sirće', 'sirce', 'musli', 'pahuljic',
            'ovsen', 'sočivo', 'socivo', 'kukuruzno brašno', 'fidelinka bras', 'proso', 'heljda',
            'kus kus', 'kuskus', 'bulgur', 'griz',
        ]],
    ];

    public function run(): void
    {
        foreach (self::TAXONOMY as $name => $definition) {
            $category = ProductCategory::updateOrCreate(
                ['name' => $name],
                ['color' => $definition['color']],
            );

            foreach ($definition['rules'] as $pattern) {
                $category->rules()->updateOrCreate(
                    ['pattern' => $pattern],
                    ['priority' => $definition['priority'] ?? 0],
                );
            }

            // Drop rules that were removed from the taxonomy so re-seeding stays idempotent.
            $category->rules()->whereNotIn('pattern', $definition['rules'])->delete();
        }
    }
}
