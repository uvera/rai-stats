<?php

namespace Tests\Feature\Livewire;

use App\Enums\ReceiptMatchSource;
use App\Enums\TransactionType;
use App\Livewire\LinkReceiptTransactionTable;
use App\Models\Account;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinkReceiptTransactionTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_suggested_charge_first_and_can_link_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$receipt, $suggested, $later] = $this->suggestedAndLaterSpend();

        Livewire::test(LinkReceiptTransactionTable::class, ['receiptId' => $receipt->id])
            ->assertCanSeeTableRecords([$suggested, $later], inOrder: true)
            ->assertSee('Suggested')
            ->callTableAction('link', $suggested)
            ->assertDispatched('grocery-receipt-linked');

        $this->assertDatabaseHas('grocery_receipts', [
            'id' => $receipt->id,
            'transaction_id' => $suggested->id,
            'match_source' => ReceiptMatchSource::Manual->value,
        ]);
    }

    public function test_search_hides_the_suggestion_badge_and_lists_newer_matches_first(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$receipt, $suggested, $later] = $this->suggestedAndLaterSpend();

        Livewire::test(LinkReceiptTransactionTable::class, ['receiptId' => $receipt->id])
            ->set('tableSearch', 'shared-note')
            ->assertCanSeeTableRecords([$later, $suggested], inOrder: true)
            ->assertDontSee('Suggested');
    }

    public function test_non_admin_cannot_open_the_link_table(): void
    {
        $this->actingAs(User::factory()->create());
        [$receipt] = $this->suggestedAndLaterSpend();

        Livewire::test(LinkReceiptTransactionTable::class, ['receiptId' => $receipt->id])
            ->assertForbidden();
    }

    /**
     * @return array{0: GroceryReceipt, 1: Transaction, 2: Transaction}
     */
    private function suggestedAndLaterSpend(): array
    {
        $owner = User::factory()->create();
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => GroceryAccount::factory()->create(['user_id' => null])->id,
            'store_name' => 'Maxi 249',
            'total_cents' => 164233,
            'purchased_at' => '2026-09-29 15:19:00',
        ]);
        $account = Account::factory()->for($owner)->create();
        $suggested = Transaction::factory()->for($account)->for($owner)->create([
            'amount_cents' => -164233,
            'date' => '2026-09-28 10:00:00',
            'type' => TransactionType::Pos,
            'place' => '213 MAXI 249 SRB',
            'description' => 'shared-note',
        ]);
        $later = Transaction::factory()->for($account)->for($owner)->create([
            'amount_cents' => -5000,
            'date' => '2026-09-30 10:00:00',
            'type' => TransactionType::Pos,
            'place' => 'Cafe',
            'description' => 'shared-note',
        ]);

        return [$receipt, $suggested, $later];
    }
}
