<x-filament::modal id="custom-modal" visible wire:model="showCustomModal">
    <x-slot name="header">
        Custom Modal
    </x-slot>

    <x-slot name="content">
        <p>This is your custom modal content.</p>
    </x-slot>

    <x-slot name="footer">
        <x-filament::button wire:click="$set('showCustomModal', false)">
            Close
        </x-filament::button>
    </x-slot>
</x-filament::modal>
