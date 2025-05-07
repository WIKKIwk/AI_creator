<x-filament::page>
    {{ $this->table }}

    @if ($showMissingAssetsModal)
        <x-filament::modal
            id="missing-assets-modal"
            :visible="$showMissingAssetsModal"
            :close-by-clicking-away="false"
            :close-by-pressing-escape="false"
        >
            <x-slot name="heading">
                Missing Assets
            </x-slot>

            <ul class="space-y-2">
                @foreach ($missingAssets as $asset)
                    <li>{{ $asset }}</li>
                @endforeach
            </ul>

            <x-slot name="footer">
                <x-filament::button wire:click="createWarehouseEntry">
                    Create Warehouse Entry
                </x-filament::button>
                <x-filament::button color="gray" wire:click="$set('showMissingAssetsModal', false)">
                    Cancel
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament::page>
