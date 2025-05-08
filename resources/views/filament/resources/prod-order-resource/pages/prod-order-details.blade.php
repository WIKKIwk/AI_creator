@php
    use App\Enums\ProdOrderStepStatus;

@endphp
<x-filament-panels::page>

    <div style="display: flex; justify-content: space-between; align-items: center">
        <x-filament::tabs style="margin-left: 0!important">
            @php
                foreach ($this->prodOrder->steps as $step):
            @endphp
            <x-filament::tabs.item
                :active="$step->id === $this->activeStep->id"
                :disabled="$step->status !== ProdOrderStepStatus::Completed && $step->id != $this->currentStep->id"
                wire:click="handleStepClick({{ $step->id }})"
            >
                {{$step->workStation->name}}
            </x-filament::tabs.item>
            @php
                endforeach
            @endphp
        </x-filament::tabs>


        <div>
                <x-filament::button
                    x-on:click="if (confirm('Are you sure?')) { $wire.call('nextStep') }"
                >
                    {{ $this->lastStep?->id == $this->currentStep->id ? 'Complete order' : 'Next Step' }}
                </x-filament::button>


                <x-filament::button
                    x-on:click="if (confirm('Are you sure?')) { $wire.call('approve') }"
                >
                    Approve order
                </x-filament::button>

        </div>

    </div>

    {{ $this->form }}
</x-filament-panels::page>
