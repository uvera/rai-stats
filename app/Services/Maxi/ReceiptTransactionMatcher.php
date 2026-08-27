<?php

namespace App\Services\Maxi;

use App\Enums\ReceiptMatchSource;
use App\Models\MaxiReceipt;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Links a Moj Maxi receipt to the bank Transaction that paid for it.
 *
 * Auto-match is deliberately strict (exact amount, tight date window, a
 * Maxi/Delhaize-looking place, exactly one candidate) so it never guesses
 * wrong; anything ambiguous is left for an admin to link by hand from the
 * candidatesFor() shortlist.
 */
class ReceiptTransactionMatcher
{
    public function autoMatch(MaxiReceipt $receipt): bool
    {
        $userId = $receipt->account->user_id;

        if ($userId === null || $receipt->transaction_id !== null) {
            return false;
        }

        $candidates = Transaction::query()
            ->where('user_id', $userId)
            ->where('amount_cents', -$receipt->total_cents)
            ->whereBetween('date', [
                $receipt->purchased_at->copy()->subDays(3)->startOfDay(),
                $receipt->purchased_at->copy()->addDays(3)->endOfDay(),
            ])
            ->where(fn (Builder $q) => $q
                ->where('place', 'ilike', '%maxi%')
                ->orWhere('place', 'ilike', '%delhaize%')
                ->orWhere('place', 'ilike', '%tempo%'))
            ->whereDoesntHave('maxiReceipt')
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
     * Recent unlinked transactions an admin can pick from to link a receipt
     * by hand - same amount within a small tolerance, a wider date window,
     * newest first.
     *
     * @return Builder<Transaction>
     */
    public function candidatesFor(MaxiReceipt $receipt, int $amountToleranceCents = 200, int $dayWindow = 10): Builder
    {
        $spend = -$receipt->total_cents;

        return Transaction::query()
            ->whereBetween('amount_cents', [$spend - $amountToleranceCents, $spend + $amountToleranceCents])
            ->whereBetween('date', [
                $receipt->purchased_at->copy()->subDays($dayWindow)->startOfDay(),
                $receipt->purchased_at->copy()->addDays($dayWindow)->endOfDay(),
            ])
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('maxiReceipt')
                ->orWhereHas('maxiReceipt', fn (Builder $r) => $r->whereKey($receipt->id)))
            ->orderByDesc('date');
    }
}
