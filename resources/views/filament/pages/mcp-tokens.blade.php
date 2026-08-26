<x-filament-panels::page>
    @if ($this->plainTextToken)
        <x-filament::section>
            <x-slot name="heading">
                Your new token
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Copy this token now - it won't be shown again.
            </p>

            <x-filament::input.wrapper style="margin-top: 0.5rem;">
                <x-filament::input
                    type="text"
                    readonly
                    value="{{ $this->plainTextToken }}"
                    onclick="this.select()"
                />
            </x-filament::input.wrapper>

            <x-filament::button color="gray" style="margin-top: 1rem;" wire:click="dismissPlainTextToken">
                Done
            </x-filament::button>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
