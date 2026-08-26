<x-filament-panels::page>
    @if ($step === 'credentials')
        <x-filament::section>
            <x-slot name="description">
                Enter your Raiffeisen (RaiOnline) internet banking credentials. Your password is
                used only for this import and is never stored.
            </x-slot>

            <form wire:submit="submitCredentials" class="max-w-md" style="max-width: 28rem;">
                {{ $this->credentialsForm }}

                <div style="margin-top: 1.5rem;">
                    <x-filament::button type="submit">Log in</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    @if ($step === 'waiting')
        <x-filament::section>
            <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300" wire:poll.2s="poll">
                <x-filament::loading-indicator class="h-5 w-5" />
                <span>{{ $waitingMessage }}</span>
            </div>
        </x-filament::section>
    @endif

    @if ($step === 'error')
        <x-filament::section>
            <div class="max-w-md space-y-4">
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
                <x-filament::button wire:click="startOver" color="gray">Try again</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($step === 'select')
        <div class="space-y-6">
            <x-filament::section>
                <form wire:submit="addRange" class="max-w-2xl space-y-4">
                    {{ $this->selectForm }}

                    @if ($rangeNotice)
                        <x-filament::badge color="warning">
                            {{ $rangeNotice }}
                        </x-filament::badge>
                    @endif

                    <div class="flex gap-2">
                        <x-filament::button type="submit" color="gray">
                            Add to queue
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="gray"
                            outlined
                            wire:click="addRangeForAllAccounts"
                        >
                            Add this range for all accounts
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            @if (count($queuedRanges) > 0)
                <x-filament::section>
                    <x-slot name="heading">Queued for import</x-slot>

                    <ul class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($queuedRanges as $i => $range)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span>
                                    <x-filament::badge color="gray">{{ $range['account_number'] }}</x-filament::badge>
                                    {{ $range['from'] }} &ndash; {{ $range['to'] }}
                                </span>

                                <x-filament::icon-button
                                    icon="heroicon-m-x-mark"
                                    color="danger"
                                    label="Remove"
                                    wire:click="removeRange({{ $i }})"
                                />
                            </li>
                        @endforeach
                    </ul>

                    <x-slot name="footer">
                        <x-filament::button wire:click="runImport" wire:loading.attr="disabled">
                            Run import
                        </x-filament::button>
                    </x-slot>
                </x-filament::section>
            @endif
        </div>
    @endif

    @if ($step === 'done')
        <x-filament::section>
            <x-slot name="heading">Import complete</x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($importResults as $result)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <span>{{ $result['description'] }} ({{ $result['account_number'] }})</span>
                        <x-filament::badge :color="$result['inserted'] > 0 ? 'success' : 'gray'">
                            {{ $result['inserted'] }} new
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>

            <x-slot name="footer">
                <x-filament::button wire:click="startOver" color="gray">Import more</x-filament::button>
            </x-slot>
        </x-filament::section>
    @endif
</x-filament-panels::page>
