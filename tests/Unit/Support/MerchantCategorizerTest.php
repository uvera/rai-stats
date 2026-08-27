<?php

namespace Tests\Unit\Support;

use App\Models\Category;
use App\Models\MerchantCategoryRule;
use App\Support\MerchantCategorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantCategorizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_a_rule_by_case_insensitive_substring(): void
    {
        $category = Category::factory()->create(['name' => 'Groceries']);
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI']);

        $categorizer = new MerchantCategorizer;

        $this->assertSame($category->id, $categorizer->categorize('213 MAXI 249 SRB 06 NOVI SAD'));
        $this->assertSame($category->id, $categorizer->categorize('213 maxi 249 srb 06 novi sad'));
    }

    public function test_returns_null_when_no_rule_matches(): void
    {
        Category::factory()->create(['name' => 'Groceries']);
        $categorizer = new MerchantCategorizer;

        $this->assertNull($categorizer->categorize('Some Random Place'));
    }

    public function test_higher_priority_rule_wins_on_overlap(): void
    {
        $specific = Category::factory()->create(['name' => 'Parking']);
        $generic = Category::factory()->create(['name' => 'Fees']);

        MerchantCategoryRule::factory()->for($generic)->create(['pattern' => 'PAYSPOT', 'priority' => 0]);
        MerchantCategoryRule::factory()->for($specific)->create(['pattern' => 'PAYSPOT  DOO*PARKING', 'priority' => 10]);

        $categorizer = new MerchantCategorizer;

        $this->assertSame($specific->id, $categorizer->categorize('PAYSPOT  DOO*PARKING S SRB NOVI SAD'));
    }
}
