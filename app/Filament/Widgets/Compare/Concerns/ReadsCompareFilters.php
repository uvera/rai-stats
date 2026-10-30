<?php

namespace App\Filament\Widgets\Compare\Concerns;

use App\Models\Account;
use App\Support\AccountSpendComparison;
use App\Support\DateFilter;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Builds the two-account comparison from the Compare page's filter bar.
 * Kept off the Dashboard by not being registered for discovery, the same
 * way the My Stats widgets are.
 */
trait ReadsCompareFilters
{
    use InteractsWithPageFilters;

    protected function comparison(): ?AccountSpendComparison
    {
        $accountAId = $this->pageFilters['accountA'] ?? null;
        $accountBId = $this->pageFilters['accountB'] ?? null;

        if (blank($accountAId) || blank($accountBId) || (int) $accountAId === (int) $accountBId) {
            return null;
        }

        $accounts = Account::query()
            ->with('user')
            ->whereIn('id', [(int) $accountAId, (int) $accountBId])
            ->get()
            ->keyBy('id');

        $accountA = $accounts->get((int) $accountAId);
        $accountB = $accounts->get((int) $accountBId);

        if ($accountA === null || $accountB === null) {
            return null;
        }

        $period = $this->pageFilters['period'] ?? null;

        return new AccountSpendComparison(
            accountA: $accountA,
            accountB: $accountB,
            from: DateFilter::parseOr($this->pageFilters['from'] ?? null, CarbonImmutable::now()->startOfYear()),
            to: DateFilter::parseOr($this->pageFilters['to'] ?? null, CarbonImmutable::now()),
            period: in_array($period, ['month', 'quarter', 'year'], true) ? $period : 'month',
        );
    }

    protected function formatSpend(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    /**
     * Two people can hold bank accounts with the same description, so the
     * person is part of every label that names an account.
     */
    protected function accountLabel(Account $account): string
    {
        return "{$account->user->name} - {$account->description}";
    }
}
