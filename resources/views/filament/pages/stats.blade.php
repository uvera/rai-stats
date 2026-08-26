<x-filament-panels::page>
    <x-filament::section>
        <div class="[&_.fi-sc]:gap-x-8 [&_.fi-sc]:gap-y-6">
            {{ $this->filtersForm }}
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
