@php
    use App\Enums\ProdOrderStepStatus;
    use App\Enums\OrderStatus;

@endphp
<x-filament-panels::page>

    <div style="display: flex; justify-content: space-between; align-items: center">
        <x-filament::tabs style="margin-left: 0!important">
            @php
                foreach ($this->prodOrder->steps as $step):
            @endphp
            <x-filament::tabs.item
                :active="$step->id === $this->activeStep->id"
                wire:click="handleStepClick({{ $step->id }})"
            >
                {{$step->workStation->name}}
            </x-filament::tabs.item>
            @php
                endforeach
            @endphp
        </x-filament::tabs>
    </div>

    {{ $this->form }}
</x-filament-panels::page>
