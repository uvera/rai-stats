<x-filament-panels::page>
    <form wire:submit.prevent class="flex flex-wrap items-end gap-4">
        <div>
            <label class="text-sm font-medium">From</label>
            <input type="date" wire:model.live="from" class="fi-input mt-1 block rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
        </div>
        <div>
            <label class="text-sm font-medium">To</label>
            <input type="date" wire:model.live="to" class="fi-input mt-1 block rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
        </div>
        <div>
            <label class="text-sm font-medium">Period</label>
            <select wire:model.live="period" class="fi-input mt-1 block rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                <option value="month">Month</option>
                <option value="quarter">Quarter</option>
                <option value="year">Year</option>
            </select>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Transactions</div>
            <div class="text-2xl font-semibold">{{ $this->transactionCount() }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Average spend</div>
            <div class="text-2xl font-semibold">{{ number_format($this->averageSpendCents() / 100, 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">ATM / cash withdrawals</div>
            <div class="text-2xl font-semibold">{{ number_format($this->atmWithdrawalTotalCents() / 100, 2) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Spend per account">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="cursor-pointer py-1" wire:click="sortAccountsBy('description')">Account</th>
                    <th class="cursor-pointer py-1 text-right" wire:click="sortAccountsBy('spend_cents')">Spend</th>
                    <th class="cursor-pointer py-1 text-right" wire:click="sortAccountsBy('income_cents')">Income</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->spendPerAccount() as $row)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1">
                            {{ $row['description'] }}
                            <span class="text-gray-400">({{ $row['number'] }})</span>
                        </td>
                        <td class="py-1 text-right text-danger-600">{{ number_format($row['spend_cents'] / 100, 2) }} {{ $row['currency_code'] }}</td>
                        <td class="py-1 text-right text-success-600">{{ number_format($row['income_cents'] / 100, 2) }} {{ $row['currency_code'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-gray-400">No transactions in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Top places">
        @php $places = $this->topPlaces(); $max = collect($places)->max('spend_cents') ?: 1; @endphp
        <div class="space-y-2">
            @forelse ($places as $place)
                <div>
                    <div class="flex justify-between text-sm">
                        <span>{{ $place['place'] }}</span>
                        <span class="text-gray-500">{{ number_format($place['spend_cents'] / 100, 2) }} ({{ $place['transaction_count'] }}x)</span>
                    </div>
                    <div class="h-2 rounded bg-gray-100 dark:bg-gray-700">
                        <div class="h-2 rounded bg-primary-500" style="width: {{ max(2, (int) ($place['spend_cents'] / $max * 100)) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-gray-400">No spending in this range.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section heading="Spend per place over time">
        @php $matrix = $this->spendPerPlaceOverTime(); @endphp
        @if (empty($matrix['periods']))
            <p class="text-center text-gray-400">No spending in this range.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-1">Place</th>
                            @foreach ($matrix['periods'] as $period)
                                <th class="py-1 text-right">{{ $this->formatPeriodLabel($period) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix['places'] as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-1">{{ $row['place'] }}</td>
                                @foreach ($matrix['periods'] as $period)
                                    <td class="py-1 text-right">{{ number_format(($row['totals'][$period] ?? 0) / 100, 2) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Income vs expense">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="py-1">Period</th>
                    <th class="py-1 text-right">Income</th>
                    <th class="py-1 text-right">Expense</th>
                    <th class="py-1 text-right">Net</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->incomeVsExpenseTrend() as $row)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1">{{ $this->formatPeriodLabel($row['period']) }}</td>
                        <td class="py-1 text-right text-success-600">{{ number_format($row['income_cents'] / 100, 2) }}</td>
                        <td class="py-1 text-right text-danger-600">{{ number_format($row['expense_cents'] / 100, 2) }}</td>
                        <td class="py-1 text-right {{ $row['net_cents'] < 0 ? 'text-danger-600' : 'text-success-600' }}">
                            {{ number_format($row['net_cents'] / 100, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-gray-400">No transactions in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-filament::section heading="Largest transactions">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-1">Date</th>
                        <th class="py-1">Place</th>
                        <th class="py-1 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->largestTransactions() as $transaction)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1">{{ $transaction->date->format('d.m.Y') }}</td>
                            <td class="py-1">{{ $transaction->place }}</td>
                            <td class="py-1 text-right {{ $transaction->amount_cents < 0 ? 'text-danger-600' : 'text-success-600' }}">
                                {{ number_format($transaction->amount_cents / 100, 2) }} {{ $transaction->currency_code }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-gray-400">No transactions in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Recurring charges">
            <p class="mb-2 text-xs text-gray-400">Same place, similar amount, 3+ months - a rough heuristic, not exact.</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-1">Place</th>
                        <th class="py-1 text-right">Months</th>
                        <th class="py-1 text-right">Avg amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->recurringCharges() as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1">{{ $row['place'] }}</td>
                            <td class="py-1 text-right">{{ $row['months'] }}</td>
                            <td class="py-1 text-right">{{ number_format($row['average_cents'] / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-gray-400">None detected in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>

    @if ($this->showLeaderboard())
        <x-filament::section heading="Leaderboard">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-1">Who</th>
                        <th class="py-1 text-right">Spend</th>
                        <th class="py-1 text-right">Income</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->leaderboard() as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1">{{ $row['name'] }}</td>
                            <td class="py-1 text-right text-danger-600">{{ number_format($row['spend_cents'] / 100, 2) }}</td>
                            <td class="py-1 text-right text-success-600">{{ number_format($row['income_cents'] / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-gray-400">No transactions in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
