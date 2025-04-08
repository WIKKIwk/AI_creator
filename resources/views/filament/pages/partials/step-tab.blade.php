<div wire:key="step-{{ $step->id }}">
    <p class="text-gray-700">You are viewing step {{ $step->id }}</p>
    @livewire('prod-order-step-required', ['step' => $step], key('step-required-' . $step->id))
    <x-filament::button wire:click="$refresh">
        Refresh
    </x-filament::button>
</div>
