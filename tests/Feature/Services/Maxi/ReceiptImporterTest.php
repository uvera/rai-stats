<?php

namespace Tests\Feature\Services\Maxi;

use App\Enums\ReceiptMatchSource;
use App\Models\Account;
use App\Models\MaxiAccount;
use App\Models\ProductCategory;
use App\Models\ProductCategoryRule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Maxi\Data\InvoiceSummary;
use App\Services\Maxi\ReceiptImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptImporterTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(): string
    {
        return file_get_contents(base_path('tests/Fixtures/maxi/ereceipt-sample.pdf'));
    }

    private function summary(string $hash = 'hash-1', ?CarbonImmutable $at = null): InvoiceSummary
    {
        return new InvoiceSummary(
            invoiceHash: $hash,
            purchasedAt: $at ?? CarbonImmutable::parse('2026-08-26 19:33'),
            totalCents: 312545,
            storeName: 'Mega Maxi 02 Novi Sad',
            storeAddress: 'Tekelijina bb',
            storeFormat: 'MEGA MAXI',
            pdfUrl: 'https://example.test/eReceipt/hash-1',
        );
    }

    public function test_imports_receipt_with_parsed_items(): void
    {
        $account = MaxiAccount::factory()->create();

        $receipt = (new ReceiptImporter)->import($account, $this->summary(), $this->pdf());

        $this->assertSame(312545, $receipt->total_cents);
        $this->assertSame('FAKE0000-FAKE0000-00001', $receipt->pfr_number);
        $this->assertNotNull($receipt->purs_vl);
        $this->assertCount(8, $receipt->items);
        $this->assertSame(312545, $receipt->items->sum('total_cents'));
    }

    public function test_reimporting_the_same_invoice_is_idempotent(): void
    {
        $account = MaxiAccount::factory()->create();
        $importer = new ReceiptImporter;

        $importer->import($account, $this->summary(), $this->pdf());
        $importer->import($account, $this->summary(), $this->pdf());

        $this->assertDatabaseCount('maxi_receipts', 1);
        $this->assertDatabaseCount('maxi_receipt_items', 8);
    }

    public function test_applies_product_category_rules_to_items(): void
    {
        $account = MaxiAccount::factory()->create();
        $dairy = ProductCategory::factory()->create(['name' => 'Dairy']);
        ProductCategoryRule::factory()->for($dairy)->create(['pattern' => 'jogurt']);

        $receipt = (new ReceiptImporter)->import($account, $this->summary(), $this->pdf());

        $this->assertSame(
            $dairy->id,
            $receipt->items->firstWhere('name', 'Jogurt 2,8% MM Zapis Tare1,5kg/KOM')->product_category_id,
        );
    }

    public function test_auto_links_a_matching_bank_transaction(): void
    {
        $user = User::factory()->create();
        $account = MaxiAccount::factory()->create(['user_id' => $user->id]);
        $bankAccount = Account::factory()->create(['user_id' => $user->id]);

        $txn = Transaction::factory()->for($bankAccount, 'account')->for($user)->create([
            'amount_cents' => -312545,
            'date' => CarbonImmutable::parse('2026-08-26 20:10'),
            'place' => 'MAXI 249 NOVI SAD',
        ]);

        $receipt = (new ReceiptImporter)->import($account, $this->summary(), $this->pdf());

        $this->assertSame($txn->id, $receipt->transaction_id);
        $this->assertSame(ReceiptMatchSource::Auto, $receipt->match_source);
    }

    public function test_does_not_auto_link_when_two_candidates_match(): void
    {
        $user = User::factory()->create();
        $account = MaxiAccount::factory()->create(['user_id' => $user->id]);
        $bankAccount = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(2)->for($bankAccount, 'account')->for($user)->create([
            'amount_cents' => -312545,
            'date' => CarbonImmutable::parse('2026-08-26 20:10'),
            'place' => 'MAXI 249',
        ]);

        $receipt = (new ReceiptImporter)->import($account, $this->summary(), $this->pdf());

        $this->assertNull($receipt->transaction_id);
    }
}
