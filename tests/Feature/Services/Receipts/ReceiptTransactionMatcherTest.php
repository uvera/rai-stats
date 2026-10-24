<?php

namespace Tests\Feature\Services\Receipts;

use App\Enums\ReceiptMatchSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Receipts\ReceiptTransactionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_auto_match_links_existing_receipts_and_skips_an_account_with_no_user(): void
    {
        $owner = User::factory()->create();
        $account = GroceryAccount::factory()->create(['user_id' => $owner->id]);
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => $account->id,
            'total_cents' => 10000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);
        $payment = $this->payment($owner, -10000, place: 'MAXI');
        $unassigned = GroceryReceipt::factory()->create([
            'grocery_account_id' => GroceryAccount::factory()->create(['user_id' => null])->id,
            'total_cents' => 10000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);

        $linked = (new ReceiptTransactionMatcher)->autoMatchUnlinked();

        $this->assertSame(1, $linked);
        $this->assertSame($payment->id, $receipt->fresh()->transaction_id);
        $this->assertSame(ReceiptMatchSource::Auto, $receipt->fresh()->match_source);
        $this->assertNull($unassigned->fresh()->transaction_id);
    }

    public function test_auto_match_for_one_account_leaves_other_receipts_unlinked(): void
    {
        $owner = User::factory()->create();
        $account = GroceryAccount::factory()->create(['user_id' => $owner->id]);
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => $account->id,
            'total_cents' => 10000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);
        $payment = $this->payment($owner, -10000, place: 'MAXI');

        $otherOwner = User::factory()->create();
        $otherAccount = GroceryAccount::factory()->create(['user_id' => $otherOwner->id]);
        $otherReceipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => $otherAccount->id,
            'total_cents' => 8000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);
        $this->payment($otherOwner, -8000, place: 'MAXI');

        $linked = (new ReceiptTransactionMatcher)->autoMatchUnlinked($account);

        $this->assertSame(1, $linked);
        $this->assertSame($payment->id, $receipt->fresh()->transaction_id);
        $this->assertNull($otherReceipt->fresh()->transaction_id);
    }

    public function test_auto_match_skips_a_receipt_with_two_fitting_charges(): void
    {
        $owner = User::factory()->create();
        $account = GroceryAccount::factory()->create(['user_id' => $owner->id]);
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => $account->id,
            'total_cents' => 10000,
            'purchased_at' => '2026-08-26 12:00:00',
        ]);
        $this->payment($owner, -10000, place: 'MAXI');
        $this->payment($owner, -10000, place: 'MAXI 2');

        $this->assertSame(0, (new ReceiptTransactionMatcher)->autoMatchUnlinked());
        $this->assertNull($receipt->fresh()->transaction_id);
    }

    public function test_linkable_transactions_stay_with_the_receipt_owner(): void
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
        $inflow = $this->payment($owner, 10000, TransactionType::Income);
        $alreadyLinked = $this->payment($owner, -5000);
        GroceryReceipt::factory()->create(['transaction_id' => $alreadyLinked->id]);

        $ids = (new ReceiptTransactionMatcher)->linkableQuery($receipt)->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
        $this->assertFalse($ids->contains($reserved->id));
        $this->assertFalse($ids->contains($inflow->id));
        $this->assertFalse($ids->contains($alreadyLinked->id));
    }

    public function test_unassigned_account_suggests_the_closer_amount_on_the_same_day(): void
    {
        $owner = User::factory()->create();
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => GroceryAccount::factory()->create(['user_id' => null])->id,
            'store_name' => 'Maxi 249',
            'total_cents' => 164233,
            'purchased_at' => '2026-09-29 15:19:00',
        ]);

        $exact = $this->payment($owner, -164233, place: '213 MAXI 249 SRB 06 NOVI SAD', date: '2026-09-29 00:00:00');
        $closerTime = $this->payment($owner, -164100, place: '213 MAXI 249 SRB', date: '2026-09-29 15:19:00');
        $tooOld = $this->payment($owner, -164233, place: '213 MAXI 249 SRB', date: '2026-08-01 00:00:00');

        $matcher = new ReceiptTransactionMatcher;

        $this->assertTrue($matcher->linkableQuery($receipt)->pluck('id')->contains($tooOld->id));
        $this->assertSame($exact->id, $matcher->potentialMatch($receipt)?->id);
        $this->assertNotSame($closerTime->id, $matcher->potentialMatch($receipt)?->id);
    }

    public function test_suggestion_prefers_a_place_that_contains_the_store_name(): void
    {
        $owner = User::factory()->create();
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => GroceryAccount::factory()->create(['user_id' => null])->id,
            'store_name' => 'Maxi 249',
            'total_cents' => 164233,
            'purchased_at' => '2026-09-29 15:19:00',
        ]);

        $this->payment($owner, -164233, place: 'Grocery shop', date: '2026-09-29 15:19:00');
        $atTheStore = $this->payment($owner, -164233, place: '213 MAXI 249 SRB', date: '2026-09-29 15:19:00');

        $this->assertSame($atTheStore->id, (new ReceiptTransactionMatcher)->potentialMatch($receipt)?->id);
    }

    public function test_suggested_transaction_is_listed_before_later_spending_until_search(): void
    {
        [$receipt, $suggested, $later] = $this->suggestedAndLaterSpend();

        $ids = (new ReceiptTransactionMatcher)->pickerQuery($receipt)->pluck('id')->all();

        $this->assertSame([$suggested->id, $later->id], $ids);
    }

    public function test_search_lists_matches_by_date_instead_of_the_suggestion(): void
    {
        [$receipt, $suggested, $later] = $this->suggestedAndLaterSpend();

        $ids = (new ReceiptTransactionMatcher)->pickerQuery($receipt, 'shared-note')->pluck('id')->all();

        $this->assertSame([$later->id, $suggested->id], $ids);
    }

    public function test_search_treats_percent_as_a_literal(): void
    {
        [$receipt] = $this->suggestedAndLaterSpend();

        $this->assertSame([], (new ReceiptTransactionMatcher)->pickerQuery($receipt, '%')->pluck('id')->all());
    }

    #[DataProvider('pickerSearchTerms')]
    public function test_search_matches_place_description_reference_account_and_amount(string $term): void
    {
        $owner = User::factory()->create();
        $receipt = GroceryReceipt::factory()->create([
            'grocery_account_id' => GroceryAccount::factory()->create(['user_id' => null])->id,
            'store_name' => 'Maxi 249',
            'total_cents' => 164233,
            'purchased_at' => '2026-09-29 15:19:00',
        ]);
        $match = $this->payment(
            $owner,
            -164233,
            place: '213 MAXI 249 SRB',
            date: '2026-09-29 00:00:00',
            description: '989334',
            reference: 'REF2491',
            accountDescription: 'Tekuci racuni',
        );
        $this->payment($owner, -5000, place: 'Kiosk', date: '2026-09-30 00:00:00', description: 'other', reference: 'other-ref', accountDescription: 'Savings');

        $ids = (new ReceiptTransactionMatcher)->pickerQuery($receipt, $term)->pluck('id')->all();

        $this->assertSame([$match->id], $ids);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pickerSearchTerms(): array
    {
        return [
            'place' => ['MAXI 249'],
            'description' => ['989334'],
            'reference' => ['REF2491'],
            'account name' => ['Tekuci'],
            'amount' => ['1642.33'],
        ];
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
        $suggested = $this->payment(
            $owner,
            -164233,
            place: '213 MAXI 249 SRB',
            date: '2026-09-28 10:00:00',
            description: 'shared-note',
        );
        $later = $this->payment(
            $owner,
            -5000,
            place: 'Cafe',
            date: '2026-09-30 10:00:00',
            description: 'shared-note',
        );

        return [$receipt, $suggested, $later];
    }

    private function payment(
        User $user,
        int $amountCents,
        TransactionType $type = TransactionType::Pos,
        string $place = 'Shop',
        string $date = '2026-08-26 12:00:00',
        ?string $description = null,
        ?string $reference = null,
        ?string $accountDescription = null,
    ): Transaction {
        $account = Account::factory()->for($user)->create([
            'description' => $accountDescription ?? 'Current account',
        ]);

        return Transaction::factory()->for($account)->for($user)->create([
            'amount_cents' => $amountCents,
            'date' => $date,
            'type' => $type,
            'place' => $place,
            'description' => $description ?? 'note',
            'reference' => $reference ?? 'ref',
        ]);
    }
}
