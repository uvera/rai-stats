<?php

namespace Tests\Feature\Services\Receipts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Receipts\ReceiptTransactionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTransactionMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_are_limited_to_the_receipt_owner_and_exclude_reserved_holds(): void
    {
        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();
        $groceryAccount = GroceryAccount::factory()->create(['user_id' => $owner->id]);
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => $groceryAccount->id,
            'total_cents' => 10000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);

        $mine = $this->payment($owner, -10000);
        $theirs = $this->payment($someoneElse, -10000);
        $reserved = $this->payment($owner, -10000, TransactionType::Reserved);

        $ids = (new ReceiptTransactionMatcher)->candidatesFor($receipt)->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
        $this->assertFalse($ids->contains($reserved->id));
    }

    public function test_auto_match_does_not_link_a_reserved_hold(): void
    {
        $owner = User::factory()->create();
        $groceryAccount = GroceryAccount::factory()->create(['user_id' => $owner->id]);
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => $groceryAccount->id,
            'total_cents' => 10000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);

        $this->payment($owner, -10000, TransactionType::Reserved, 'MAXI');

        $this->assertFalse((new ReceiptTransactionMatcher)->autoMatch($receipt->fresh()));
        $this->assertNull($receipt->fresh()->transaction_id);
    }

    private function payment(User $user, int $amountCents, TransactionType $type = TransactionType::Pos, string $place = 'Shop'): Transaction
    {
        $account = Account::factory()->for($user)->create();

        return Transaction::factory()->for($account)->for($user)->create([
            'amount_cents' => $amountCents,
            'date' => '2026-08-26 12:00:00',
            'type' => $type,
            'place' => $place,
        ]);
    }
}
