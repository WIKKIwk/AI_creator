<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$activeTab == 1"
            wire:click="$set('activeTab', 1)"
        >
            Tab 1
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab == 2"
            wire:click="$set('activeTab', 2)"
        >
            Tab 2
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab == 3"
            wire:click="$set('activeTab', 3)"
        >
            Tab 3
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{ $this->infolist }}
</x-filament-panels::page>
