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
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <x-filament::section>
                <x-slot name="heading">Guided import</x-slot>
                <x-slot name="description">
                    Pick accounts and a year - a Jan&ndash;Jun and a Jul&ndash;Dec range will be queued
                    for each, skipping whatever's already imported.
                </x-slot>

                <form wire:submit="queueGuidedImport" style="max-width: 42rem;">
                    {{ $this->guidedForm }}

                    <div style="margin-top: 1.5rem;">
                        <x-filament::button type="submit" color="gray">
                            Queue guided import
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Manual range</x-slot>

                <form wire:submit="addRange" style="max-width: 42rem;">
                    {{ $this->selectForm }}

                    @if ($rangeNotice)
                        <div style="margin-top: 1rem;">
                            <x-filament::badge color="warning">
                                {{ $rangeNotice }}
                            </x-filament::badge>
                        </div>
                    @endif

                    <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
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
                <div style="display: flex; gap: 0.5rem;">
                    <x-filament::button wire:click="continueImporting">
                        Import more (same login)
                    </x-filament::button>

                    <x-filament::button wire:click="startOver" color="gray" outlined>
                        Log out &amp; start over
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::section>
    @endif
</x-filament-panels::page>
