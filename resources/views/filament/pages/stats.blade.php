<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-64">
                {{ $this->dateFilters }}
            </div>
            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white">Period</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="filters.period">
                        <option value="month">Month</option>
                        <option value="quarter">Quarter</option>
                        <option value="year">Year</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div class="min-w-64">
                {{ $this->accountsFilter }}
            </div>
        </div>
    </x-filament::section>

    <x-filament-widgets::widgets
        :widgets="$this->getStatsWidgets()"
        :data="$this->widgetData()"
        :columns="1"
    />

    <x-filament-widgets::widgets
        :widgets="$this->getChartWidgets()"
        :data="$this->widgetData()"
        :columns="['default' => 1, 'lg' => 2]"
    />

    <x-filament-widgets::widgets
        :widgets="$this->getTableWidgets()"
        :data="$this->widgetData()"
        :columns="1"
    />
</x-filament-panels::page>
