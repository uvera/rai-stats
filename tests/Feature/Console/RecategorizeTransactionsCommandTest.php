<?php

namespace Tests\Feature\Console;

use App\Enums\CategorySource;
use App\Models\Account;
use App\Models\Category;
use App\Models\MerchantCategoryRule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecategorizeTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(string $place): Transaction
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        return Transaction::factory()->for($account)->for($user)->create(['place' => $place]);
    }

    public function test_recategorizes_transactions_matching_a_rule(): void
    {
        $category = Category::factory()->create(['name' => 'Groceries']);
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI']);

        $transaction = $this->makeTransaction('213 MAXI 249 SRB NOVI SAD');

        Artisan::call('transactions:recategorize');

        $transaction->refresh();
        $this->assertSame($category->id, $transaction->category_id);
        $this->assertSame(CategorySource::Rule, $transaction->category_source);
    }

    public function test_dry_run_makes_no_database_changes(): void
    {
        $category = Category::factory()->create();
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI']);

        $transaction = $this->makeTransaction('213 MAXI 249 SRB NOVI SAD');

        Artisan::call('transactions:recategorize', ['--dry-run' => true]);

        $transaction->refresh();
        $this->assertNull($transaction->category_id);
    }

    public function test_manually_categorized_transactions_are_skipped_unless_forced(): void
    {
        $ruleCategory = Category::factory()->create(['name' => 'Groceries']);
        $manualCategory = Category::factory()->create(['name' => 'Special']);
        MerchantCategoryRule::factory()->for($ruleCategory)->create(['pattern' => 'MAXI']);

        $transaction = $this->makeTransaction('213 MAXI 249 SRB NOVI SAD');
        $transaction->update(['category_id' => $manualCategory->id, 'category_source' => CategorySource::Manual]);

        Artisan::call('transactions:recategorize');

        $transaction->refresh();
        $this->assertSame($manualCategory->id, $transaction->category_id);

        Artisan::call('transactions:recategorize', ['--force' => true]);

        $transaction->refresh();
        $this->assertSame($ruleCategory->id, $transaction->category_id);
        $this->assertSame(CategorySource::Rule, $transaction->category_source);
    }
}
