<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared query layer behind the My Stats / Family Stats pages, so
 * period-grouping and scoping logic isn't duplicated per widget. Every
 * query excludes reserved/pending transactions (not final amounts, no
 * stable bank ID - see Transaction::scopeExcludingReserved).
 */
readonly class TransactionStats
{
    /**
     * @param  int|null  $userId  Null = family scope (every user); an id = that user only.
     * @param  'month'|'quarter'|'year'  $period
     */
    public function __construct(
        private ?int $userId,
        private CarbonImmutable $from,
        private CarbonImmutable $to,
        private string $period = 'month',
    ) {}

    private function baseQuery(): Builder
    {
        // Columns qualified with the table name throughout this class:
        // several methods join "accounts", which also has a user_id column,
        // so an unqualified "user_id" is ambiguous once that join is added.
        return Transaction::query()
            ->excludingReserved()
            ->whereBetween('transactions.date', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->when($this->userId !== null, fn (Builder $q) => $q->where('transactions.user_id', $this->userId));
    }

    public function formatPeriod(string $period): string
    {
        $date = CarbonImmutable::parse($period);

        return match ($this->period) {
            'quarter' => 'Q'.$date->quarter.' '.$date->year,
            'year' => (string) $date->year,
            default => $date->format('M Y'),
        };
    }

    private function periodTruncSql(): string
    {
        $unit = match ($this->period) {
            'quarter' => 'quarter',
            'year' => 'year',
            default => 'month',
        };

        return "date_trunc('{$unit}', date)";
    }

    /**
     * @return array<int, array{account_id: int, number: string, description: string, currency_code: string, spend_cents: int, income_cents: int}>
     */
    public function spendPerAccount(): array
    {
        return $this->baseQuery()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->groupBy('accounts.id', 'accounts.number', 'accounts.description', 'accounts.currency_code')
            ->selectRaw('accounts.id as account_id, accounts.number, accounts.description, accounts.currency_code')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents < 0 THEN -amount_cents ELSE 0 END), 0) as spend_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents ELSE 0 END), 0) as income_cents')
            ->orderByDesc('spend_cents')
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->all();
    }

    /**
     * Same data as spendPerAccount(), but as a real per-Account Eloquent
     * query (via withSum on the transactions relation) rather than a plain
     * array, so it can drive a native, sortable Filament table.
     */
    public function spendPerAccountQuery(): Builder
    {
        return Account::query()
            ->whereHas('transactions', fn (Builder $q) => $this->constrainTransactions($q))
            ->withSum(['transactions as spend_cents' => fn (Builder $q) => $this->constrainTransactions($q)->where('amount_cents', '<', 0)], DB::raw('-amount_cents'))
            ->withSum(['transactions as income_cents' => fn (Builder $q) => $this->constrainTransactions($q)->where('amount_cents', '>', 0)], 'amount_cents')
            ->orderByDesc('spend_cents');
    }

    /**
     * Family Stats only: same totals as leaderboard(), but as a real
     * per-User Eloquent query for a native Filament table.
     */
    public function leaderboardQuery(): Builder
    {
        return User::query()
            ->whereHas('transactions', fn (Builder $q) => $q
                ->excludingReserved()
                ->whereBetween('date', [$this->from->startOfDay(), $this->to->endOfDay()]))
            ->withSum(['transactions as spend_cents' => fn (Builder $q) => $q
                ->excludingReserved()
                ->whereBetween('date', [$this->from->startOfDay(), $this->to->endOfDay()])
                ->where('amount_cents', '<', 0)], DB::raw('-amount_cents'))
            ->withSum(['transactions as income_cents' => fn (Builder $q) => $q
                ->excludingReserved()
                ->whereBetween('date', [$this->from->startOfDay(), $this->to->endOfDay()])
                ->where('amount_cents', '>', 0)], 'amount_cents')
            ->orderByDesc('spend_cents');
    }

    /**
     * Same rows as largestTransactions(), as a query for a native table.
     */
    public function largestTransactionsQuery(): Builder
    {
        return $this->baseQuery()->orderByRaw('ABS(amount_cents) DESC');
    }

    /**
     * Same groups as recurringCharges(), as a query for a native table.
     * "id" is synthetic (MIN(id) per place group) - these rows aren't real
     * individual transactions, just enough of a stable per-row identity for
     * Filament's table to key on.
     */
    public function recurringChargesQuery(int $minMonths = 3): Builder
    {
        return $this->baseQuery()
            ->where('amount_cents', '<', 0)
            ->groupBy('place')
            ->selectRaw('MIN(id) as id, place')
            ->selectRaw("COUNT(DISTINCT date_trunc('month', date)) as months")
            ->selectRaw('AVG(-amount_cents) as average_cents')
            ->having(DB::raw("COUNT(DISTINCT date_trunc('month', date))"), '>=', $minMonths)
            ->havingRaw('COALESCE(STDDEV(-amount_cents), 0) <= AVG(-amount_cents) * 0.15')
            ->orderByDesc('months');
    }

    private function constrainTransactions(Builder $query): Builder
    {
        return $query
            ->excludingReserved()
            ->whereBetween('date', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->when($this->userId !== null, fn (Builder $q) => $q->where('user_id', $this->userId));
    }

    /**
     * @return array<int, array{place: string, spend_cents: int, transaction_count: int}>
     */
    public function topPlaces(int $limit = 10): array
    {
        return $this->baseQuery()
            ->where('amount_cents', '<', 0)
            ->groupBy('place')
            ->selectRaw('place, SUM(-amount_cents) as spend_cents, COUNT(*) as transaction_count')
            ->orderByDesc('spend_cents')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->all();
    }

    /**
     * Rows for the top N spend places, one column per period bucket.
     *
     * @return array{periods: array<int, string>, places: array<int, array{place: string, totals: array<string, int>}>}
     */
    public function spendPerPlaceOverTime(int $topPlaces = 5): array
    {
        $places = collect($this->topPlaces($topPlaces))->pluck('place')->all();

        if ($places === []) {
            return ['periods' => [], 'places' => []];
        }

        $rows = $this->baseQuery()
            ->whereIn('place', $places)
            ->where('amount_cents', '<', 0)
            ->groupBy('place')
            ->groupByRaw($this->periodTruncSql())
            ->selectRaw("place, {$this->periodTruncSql()} as period, SUM(-amount_cents) as spend_cents")
            ->orderBy('period')
            ->get();

        $periods = $rows->pluck('period')->map(fn ($p) => (string) $p)->unique()->sort()->values()->all();

        $places = collect($places)->map(function (string $place) use ($rows, $periods) {
            $forPlace = $rows->where('place', $place);

            return [
                'place' => $place,
                'totals' => collect($periods)->mapWithKeys(
                    fn (string $period) => [$period => (int) ($forPlace->firstWhere('period', $period)->spend_cents ?? 0)]
                )->all(),
            ];
        })->all();

        return ['periods' => $periods, 'places' => $places];
    }

    /**
     * @return array<int, array{period: string, income_cents: int, expense_cents: int, net_cents: int}>
     */
    public function incomeVsExpenseTrend(): array
    {
        return $this->baseQuery()
            ->groupByRaw($this->periodTruncSql())
            ->selectRaw("{$this->periodTruncSql()} as period")
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents ELSE 0 END), 0) as income_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents < 0 THEN -amount_cents ELSE 0 END), 0) as expense_cents')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as net_cents')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->all();
    }

    /**
     * ATM/cash withdrawals aren't a distinct Raiffeisen transaction type -
     * they come through as type=Other with a telltale place/description.
     * Flagged separately because money leaves the account untracked once
     * it's cash in hand.
     */
    public function atmWithdrawalTotalCents(): int
    {
        return (int) $this->baseQuery()
            ->where('type', TransactionType::Other)
            ->where(fn (Builder $q) => $q
                ->where('place', 'ilike', '%bankomat%')
                ->orWhere('place', 'ilike', '%atm%')
                ->orWhere('description', 'ilike', '%atm%')
                ->orWhere('description', 'ilike', '%withdrawal%'))
            ->where('amount_cents', '<', 0)
            ->sum(DB::raw('-amount_cents'));
    }

    /**
     * @return array<int, Transaction>
     */
    public function largestTransactions(int $limit = 10): array
    {
        return $this->baseQuery()
            ->with('account')
            ->orderByRaw('ABS(amount_cents) DESC')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function averageSpendCents(): int
    {
        return (int) $this->baseQuery()
            ->where('amount_cents', '<', 0)
            ->avg(DB::raw('-amount_cents'));
    }

    public function transactionCount(): int
    {
        return $this->baseQuery()->count();
    }

    /**
     * Same place recurring across several distinct months with a roughly
     * stable amount - a cheap heuristic for subscriptions/regular bills,
     * not a precise match.
     *
     * @return array<int, array{place: string, months: int, average_cents: int}>
     */
    public function recurringCharges(int $minMonths = 3): array
    {
        return $this->baseQuery()
            ->where('amount_cents', '<', 0)
            ->groupBy('place')
            ->selectRaw('place')
            ->selectRaw("COUNT(DISTINCT date_trunc('month', date)) as months")
            ->selectRaw('AVG(-amount_cents) as average_cents')
            ->selectRaw('STDDEV(-amount_cents) as amount_stddev')
            ->having(DB::raw("COUNT(DISTINCT date_trunc('month', date))"), '>=', $minMonths)
            ->havingRaw('COALESCE(STDDEV(-amount_cents), 0) <= AVG(-amount_cents) * 0.15')
            ->orderByDesc('months')
            ->get()
            ->map(fn ($row) => [
                'place' => $row->place,
                'months' => (int) $row->months,
                'average_cents' => (int) round($row->average_cents),
            ])
            ->all();
    }

    /**
     * Family Stats only: per-user totals within the same filters, ignoring
     * the constructor's userId (this is the one query that's explicitly
     * cross-user regardless of scope).
     *
     * @return array<int, array{user_id: int, name: string, spend_cents: int, income_cents: int}>
     */
    public function leaderboard(): array
    {
        return Transaction::query()
            ->excludingReserved()
            ->whereBetween('date', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as user_id, users.name')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents < 0 THEN -amount_cents ELSE 0 END), 0) as spend_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents ELSE 0 END), 0) as income_cents')
            ->orderByDesc('spend_cents')
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->all();
    }
}
