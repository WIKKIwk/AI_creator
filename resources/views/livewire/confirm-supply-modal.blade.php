@php use Illuminate\Support\Arr; @endphp
<x-filament::modal width="lg" :id="'confirm-supply-modal'">
    <x-slot name="header"><b>Insufficient assets</b></x-slot>
    <div class="space-y-4">
        <p>These assets are not available in the warehouse:</p>

        @foreach ($missingAssets as $productId => $item)
            @php
                $product = Arr::get($item, 'product');
                $qty = Arr::get($item, 'quantity');
                $measureUnit = Arr::get($item, 'measure_unit');
            @endphp
            <b class='text-red-500'>{{ $product['name'] }}: {{ $qty }} {{ $measureUnit }}</b><br>
        @endforeach

        <p>Would you like to create Supply Orders for these assets?</p>
    </div>
    <x-slot name="footer">
        <div class="flex justify-end">
            <x-filament::button color="primary" wire:click="confirmSupply">Confirm</x-filament::button>
            &nbsp;
            <x-filament::button color="danger" wire:click="cancelSupply">Cancel</x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>
