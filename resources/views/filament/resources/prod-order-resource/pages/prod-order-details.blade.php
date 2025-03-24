@php use App\Enums\OrderStatus; @endphp
<x-filament-panels::page>

    <form wire:submit.prevent="submit">
        {{ $this->form }}
    </form>

    @if($this->prodOrder->status == OrderStatus::Completed && $this->prodOrder->status != OrderStatus::Approved)
        <div style="display: flex; justify-content: flex-end;">
            <x-filament::button wire:click="approve" class="mt-4">Approve</x-filament::button>
        </div>
    @endif

</x-filament-panels::page>
