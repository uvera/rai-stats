<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchSource;
use App\Models\GroceryAccount;
use App\Models\GroceryReceipt;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Links a grocery receipt to the bank Transaction that paid for it.
 *
 * Auto-match is deliberately strict (exact amount, tight date window, a
 * grocery-looking place, exactly one candidate, and only when the grocery
 * account names a user) so it never guesses wrong. An admin links anything
 * else by hand from linkableQuery(). A blank "match against" user disables
 * auto-match only; the manual list still includes every outflow.
 */
class ReceiptTransactionMatcher
{
    public function autoMatch(GroceryReceipt $receipt): bool
    {
        $userId = $receipt->account->user_id;

        if ($userId === null || $receipt->transaction_id !== null) {
            return false;
        }

        $candidates = Transaction::query()
            ->where('user_id', $userId)
            ->excludingReserved()
            ->where('amount_cents', -$receipt->total_cents)
            ->whereBetween('date', [
                $receipt->purchased_at->copy()->subDays(3)->startOfDay(),
                $receipt->purchased_at->copy()->addDays(3)->endOfDay(),
            ])
            ->where(fn (Builder $q) => $q
                ->where('place', 'ilike', '%maxi%')
                ->orWhere('place', 'ilike', '%delhaize%')
                ->orWhere('place', 'ilike', '%tempo%')
                ->orWhere('place', 'ilike', '%metro%'))
            ->whereDoesntHave('groceryReceipt')
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            return false;
        }

        $receipt->update([
            'transaction_id' => $candidates->first()->id,
            'match_source' => ReceiptMatchSource::Auto,
        ]);

        return true;
    }

    /**
     * Runs auto-match on receipts already imported. Pass an account to limit
     * the run to that account's receipts.
     */
    public function autoMatchUnlinked(?GroceryAccount $account = null): int
    {
        $linked = 0;

        GroceryReceipt::query()
            ->whereNull('transaction_id')
            ->when(
                $account !== null,
                fn (Builder $query) => $query->where('grocery_account_id', $account->id),
            )
            ->with('account')
            ->lazyById()
            ->each(function (GroceryReceipt $receipt) use (&$linked): void {
                if ($this->autoMatch($receipt)) {
                    $linked++;
                }
            });

        return $linked;
    }

    /**
     * Recent unlinked transactions an admin can pick from to link a receipt
     * by hand - same amount within a small tolerance, a wider date window,
     * newest first.
     *
     * @return Builder<Transaction>
     */
    public function candidatesFor(GroceryReceipt $receipt, int $amountToleranceCents = 200, int $dayWindow = 10): Builder
    {
        $userId = $receipt->account->user_id;
        $spend = -$receipt->total_cents;

        return Transaction::query()
            ->when(
                $userId === null,
                fn (Builder $query) => $query->whereRaw('0 = 1'),
                fn (Builder $query) => $query->where('user_id', $userId),
            )
            ->excludingReserved()
            ->whereBetween('amount_cents', [$spend - $amountToleranceCents, $spend + $amountToleranceCents])
            ->whereBetween('date', [
                $receipt->purchased_at->copy()->subDays($dayWindow)->startOfDay(),
                $receipt->purchased_at->copy()->addDays($dayWindow)->endOfDay(),
            ])
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('groceryReceipt')
                ->orWhereHas('groceryReceipt', fn (Builder $r) => $r->whereKey($receipt->id)))
            ->orderByDesc('date');
    }

    /**
     * Outflows an admin can attach to this receipt. Scoped to the grocery
     * account's user when one is set; otherwise every user's spending,
     * because a missing user only means "don't auto-link".
     *
     * @return Builder<Transaction>
     */
    public function linkableQuery(GroceryReceipt $receipt): Builder
    {
        $userId = $receipt->account->user_id;

        return Transaction::query()
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->excludingReserved()
            ->where('amount_cents', '<', 0)
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('groceryReceipt')
                ->orWhereHas('groceryReceipt', fn (Builder $linked) => $linked->whereKey($receipt->id)));
    }

    /**
     * The closest unlinked outflow within ±2 RSD and ±10 days: nearest
     * amount, then nearest time, then a place that contains the store name.
     */
    public function potentialMatch(GroceryReceipt $receipt, int $amountToleranceCents = 200, int $dayWindow = 10): ?Transaction
    {
        $spend = -$receipt->total_cents;
        $purchasedAt = $receipt->purchased_at;

        return $this->linkableQuery($receipt)
            ->whereBetween('amount_cents', [$spend - $amountToleranceCents, $spend + $amountToleranceCents])
            ->whereBetween('date', [
                $purchasedAt->copy()->subDays($dayWindow)->startOfDay(),
                $purchasedAt->copy()->addDays($dayWindow)->endOfDay(),
            ])
            ->get()
            ->sortBy(fn (Transaction $transaction): array => [
                abs($transaction->amount_cents - $spend),
                (int) abs($transaction->date->diffInSeconds($purchasedAt)),
                $this->placeMatchesStore($transaction, $receipt) ? 0 : 1,
                $transaction->id,
            ])
            ->first();
    }

    /**
     * Rows for the link modal. The potential match stays first until the
     * admin types a search; a search lists matches newest first.
     *
     * @return Builder<Transaction>
     */
    public function pickerQuery(GroceryReceipt $receipt, ?string $search = null): Builder
    {
        $query = $this->linkableQuery($receipt);

        if (filled($search)) {
            $this->constrainPickerSearch($query, $search);

            return $query->orderByDesc('transactions.date')->orderByDesc('transactions.id');
        }

        $matchId = $this->potentialMatch($receipt)?->id;

        if ($matchId !== null) {
            $query->orderByRaw('case when transactions.id = ? then 0 else 1 end', [$matchId]);
        }

        return $query->orderByDesc('transactions.date')->orderByDesc('transactions.id');
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private function constrainPickerSearch(Builder $query, string $search): void
    {
        $like = '%'.$this->escapeLike($search).'%';
        $amountSearch = str_replace([',', ' '], '', $search);

        $query->where(function (Builder $query) use ($like, $amountSearch): void {
            $query->where('place', 'ilike', $like)
                ->orWhere('description', 'ilike', $like)
                ->orWhere('reference', 'ilike', $like)
                ->orWhereHas('account', fn (Builder $account): Builder => $account->where('description', 'ilike', $like));

            if (preg_match('/\d/', $amountSearch) === 1) {
                $query->orWhereRaw(
                    '(abs(amount_cents) / 100.0)::text ilike ?',
                    ['%'.$this->escapeLike($amountSearch).'%'],
                );
            }
        });
    }

    private function placeMatchesStore(Transaction $transaction, GroceryReceipt $receipt): bool
    {
        $store = mb_strtolower(trim($receipt->store_name));
        $place = mb_strtolower(trim((string) $transaction->place));

        if ($store === '' || $place === '') {
            return false;
        }

        return str_contains($place, $store);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
