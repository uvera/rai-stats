<x-filament-panels::page>
    @if ($step === 'credentials')
        <form wire:submit="submitCredentials" class="max-w-md space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Enter your Raiffeisen (RaiOnline) internet banking credentials. Your password is
                used only for this import and is never stored.
            </p>

            <div>
                <label class="text-sm font-medium">Username</label>
                <input type="text" wire:model="username" class="fi-input mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
                @error('username') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Password</label>
                <input type="password" wire:model="password" class="fi-input mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
                @error('password') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
            </div>

            <x-filament::button type="submit">Log in</x-filament::button>
        </form>
    @endif

    @if ($step === 'waiting')
        <div wire:poll.2s="poll" class="flex items-center gap-3 text-gray-600 dark:text-gray-300">
            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span>{{ $waitingMessage }}</span>
        </div>
    @endif

    @if ($step === 'error')
        <div class="max-w-md space-y-4">
            <p class="text-sm text-danger-600">{{ $errorMessage }}</p>
            <x-filament::button wire:click="startOver" color="gray">Try again</x-filament::button>
        </div>
    @endif

    @if ($step === 'select')
        <div class="space-y-6">
            <div class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium">Account</label>
                    <select wire:model="selectedAccountNumber" class="fi-input mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                        @foreach ($accounts as $account)
                            <option value="{{ $account['number'] }}">
                                {{ $account['description'] }} ({{ $account['currency_code'] }}, {{ $account['number'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">From</label>
                    <input type="date" wire:model="fromDate" class="fi-input mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
                </div>
                <div>
                    <label class="text-sm font-medium">To</label>
                    <input type="date" wire:model="toDate" class="fi-input mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
                </div>
            </div>

            @error('fromDate') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            @error('toDate') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

            <x-filament::button wire:click="addRange" color="gray">Add to queue</x-filament::button>

            @if ($rangeNotice)
                <p class="text-sm text-warning-600">{{ $rangeNotice }}</p>
            @endif

            @if (count($queuedRanges) > 0)
                <div class="max-w-2xl space-y-2">
                    <h3 class="text-sm font-medium">Queued for import</h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700">
                        @foreach ($queuedRanges as $i => $range)
                            <li class="flex items-center justify-between px-4 py-2 text-sm">
                                <span>{{ $range['account_number'] }}: {{ $range['from'] }} &ndash; {{ $range['to'] }}</span>
                                <button type="button" wire:click="removeRange({{ $i }})" class="text-danger-600 hover:underline">Remove</button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <x-filament::button wire:click="runImport" wire:loading.attr="disabled">
                    Run import
                </x-filament::button>
            @endif
        </div>
    @endif

    @if ($step === 'done')
        <div class="max-w-2xl space-y-4">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700">
                @foreach ($importResults as $result)
                    <li class="px-4 py-2 text-sm">
                        {{ $result['description'] }} ({{ $result['account_number'] }}):
                        <strong>{{ $result['inserted'] }}</strong> new transaction(s) imported.
                    </li>
                @endforeach
            </ul>

            <x-filament::button wire:click="startOver" color="gray">Import more</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
