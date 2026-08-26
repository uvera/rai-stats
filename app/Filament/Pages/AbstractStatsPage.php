<?php

namespace App\Filament\Pages;

use App\Support\TransactionStats;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

/**
 * Shared filter bar (date range + period toggle) and widget data behind
 * both My Stats and Family Stats - the two pages differ only in whether
 * the underlying TransactionStats is scoped to the current user or to
 * everyone, and whether the cross-user leaderboard is shown.
 */
abstract class AbstractStatsPage extends Page
{
    protected string $view = 'filament.pages.stats';

    public string $from;

    public string $to;

    /** @var 'month'|'quarter'|'year' */
    public string $period = 'month';

    public string $accountSortField = 'spend_cents';

    public string $accountSortDirection = 'desc';

    public function mount(): void
    {
        $this->to = now()->format('Y-m-d');
        $this->from = now()->subMonths(6)->startOfMonth()->format('Y-m-d');
    }

    abstract protected function scopeUserId(): ?int;

    abstract public function showLeaderboard(): bool;

    public function sortAccountsBy(string $field): void
    {
        if ($this->accountSortField === $field) {
            $this->accountSortDirection = $this->accountSortDirection === 'desc' ? 'asc' : 'desc';
        } else {
            $this->accountSortField = $field;
            $this->accountSortDirection = 'desc';
        }
    }

    protected function stats(): TransactionStats
    {
        return new TransactionStats(
            userId: $this->scopeUserId(),
            from: CarbonImmutable::parse($this->from),
            to: CarbonImmutable::parse($this->to),
            period: $this->period,
        );
    }

    /**
     * @return array<int, array{account_id: int, number: string, description: string, currency_code: string, spend_cents: int, income_cents: int}>
     */
    public function spendPerAccount(): array
    {
        $rows = $this->stats()->spendPerAccount();

        usort($rows, fn ($a, $b) => $this->accountSortDirection === 'desc'
            ? $b[$this->accountSortField] <=> $a[$this->accountSortField]
            : $a[$this->accountSortField] <=> $b[$this->accountSortField]);

        return $rows;
    }

    public function topPlaces(): array
    {
        return $this->stats()->topPlaces();
    }

    public function spendPerPlaceOverTime(): array
    {
        return $this->stats()->spendPerPlaceOverTime();
    }

    public function incomeVsExpenseTrend(): array
    {
        return $this->stats()->incomeVsExpenseTrend();
    }

    public function atmWithdrawalTotalCents(): int
    {
        return $this->stats()->atmWithdrawalTotalCents();
    }

    public function largestTransactions(): array
    {
        return $this->stats()->largestTransactions();
    }

    public function averageSpendCents(): int
    {
        return $this->stats()->averageSpendCents();
    }

    public function transactionCount(): int
    {
        return $this->stats()->transactionCount();
    }

    public function recurringCharges(): array
    {
        return $this->stats()->recurringCharges();
    }

    public function leaderboard(): array
    {
        return $this->stats()->leaderboard();
    }

    public function formatPeriodLabel(string $period): string
    {
        $date = CarbonImmutable::parse($period);

        return match ($this->period) {
            'quarter' => 'Q'.$date->quarter.' '.$date->year,
            'year' => (string) $date->year,
            default => $date->format('M Y'),
        };
    }
}
