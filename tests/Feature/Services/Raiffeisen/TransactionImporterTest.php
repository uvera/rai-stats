<?php

namespace Tests\Feature\Services\Raiffeisen;

use App\Enums\CategorySource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\MerchantCategoryRule;
use App\Models\Transaction as TransactionModel;
use App\Models\User;
use App\Services\Raiffeisen\Data\ReservedTransaction;
use App\Services\Raiffeisen\Data\Transaction;
use App\Services\Raiffeisen\Data\TransactionType as RaiffeisenTransactionType;
use App\Services\Raiffeisen\TransactionImporter;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): Account
    {
        $user = User::factory()->create();

        return Account::create([
            'user_id' => $user->id,
            'number' => '265000000000000000',
            'description' => 'Test account',
            'currency_code' => 'RSD',
            'currency_code_numeric' => '941',
            'product_core_id' => '33',
        ]);
    }

    private function transactionDto(string $bankId, int $amountCents, string $place = 'Test Place'): Transaction
    {
        return new Transaction(
            currencyCodeNumeric: '941',
            currencyCode: 'RSD',
            date: new DateTimeImmutable('2026-01-15 10:00:00'),
            place: $place,
            reference: 'ref-1',
            amountCents: $amountCents,
            description: 'desc',
            bankTransactionId: $bankId,
            type: RaiffeisenTransactionType::Pos,
        );
    }

    public function test_imports_transactions(): void
    {
        $account = $this->makeAccount();
        $importer = new TransactionImporter;

        $inserted = $importer->importTurnover($account, $account->user_id, [
            $this->transactionDto('bank-id-1', -2000000),
            $this->transactionDto('bank-id-2', 13975000),
        ]);

        $this->assertSame(2, $inserted);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['bank_transaction_id' => 'bank-id-1', 'amount_cents' => -2000000]);
    }

    public function test_reimporting_the_same_transactions_does_not_duplicate(): void
    {
        $account = $this->makeAccount();
        $importer = new TransactionImporter;

        $transactions = [
            $this->transactionDto('bank-id-1', -2000000),
            $this->transactionDto('bank-id-2', 13975000),
        ];

        $importer->importTurnover($account, $account->user_id, $transactions);
        $secondRunInserted = $importer->importTurnover($account, $account->user_id, $transactions);

        $this->assertSame(0, $secondRunInserted);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_transactions_without_a_bank_id_dedupe_on_a_content_hash(): void
    {
        $account = $this->makeAccount();
        $importer = new TransactionImporter;

        $noIdTransaction = new Transaction(
            currencyCodeNumeric: '941',
            currencyCode: 'RSD',
            date: new DateTimeImmutable('2026-01-15 10:00:00'),
            place: 'Some Place',
            reference: '',
            amountCents: -1000,
            description: '',
            bankTransactionId: '',
            type: RaiffeisenTransactionType::Other,
        );

        $importer->importTurnover($account, $account->user_id, [$noIdTransaction]);
        $secondRunInserted = $importer->importTurnover($account, $account->user_id, [$noIdTransaction]);

        $this->assertSame(0, $secondRunInserted);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_imports_get_categorized_when_a_rule_matches(): void
    {
        $category = Category::factory()->create(['name' => 'Groceries']);
        MerchantCategoryRule::factory()->for($category)->create(['pattern' => 'MAXI']);

        $account = $this->makeAccount();
        $importer = new TransactionImporter;

        $importer->importTurnover($account, $account->user_id, [
            $this->transactionDto('bank-id-1', -2000000, '213 MAXI 249 SRB NOVI SAD'),
            $this->transactionDto('bank-id-2', -1000, 'Unrelated Place'),
        ]);

        $this->assertDatabaseHas('transactions', [
            'bank_transaction_id' => 'bank-id-1',
            'category_id' => $category->id,
            'category_source' => CategorySource::Rule->value,
        ]);
        $this->assertDatabaseHas('transactions', [
            'bank_transaction_id' => 'bank-id-2',
            'category_id' => null,
            'category_source' => null,
        ]);
    }

    public function test_imports_reserved_funds_tagged_as_reserved(): void
    {
        $account = $this->makeAccount();
        $importer = new TransactionImporter;

        $reserved = new ReservedTransaction(
            date: new DateTimeImmutable('2026-01-15 10:00:00'),
            place: 'Pending POS',
            amountCents: -5000,
            currencyCode: 'RSD',
            currencyCodeNumeric: '941',
        );

        $inserted = $importer->importReserved($account, $account->user_id, [$reserved]);

        $this->assertSame(1, $inserted);
        $this->assertDatabaseHas('transactions', ['type' => TransactionType::Reserved->value]);
    }

    public function test_reserved_transactions_are_excluded_from_the_default_stats_scope(): void
    {
        $account = $this->makeAccount();
        $importer = new TransactionImporter;

        $importer->importTurnover($account, $account->user_id, [$this->transactionDto('bank-id-1', -1000)]);
        $importer->importReserved($account, $account->user_id, [
            new ReservedTransaction(new DateTimeImmutable('2026-01-15'), 'Pending', -500, 'RSD', '941'),
        ]);

        $this->assertSame(1, TransactionModel::excludingReserved()->count());
        $this->assertSame(2, TransactionModel::count());
    }
}
