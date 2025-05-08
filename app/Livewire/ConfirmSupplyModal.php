<?php

namespace App\Livewire;

use Throwable;
use Livewire\Component;
use Illuminate\View\View;
use App\Models\ProdOrder;
use App\Services\ProdOrderService;
use Filament\Notifications\Notification;

class ConfirmSupplyModal extends Component
{
    public ProdOrder $prodOrder;
    public $missingAssets = [];
    public $content;

    protected $listeners = ['openModal'];

    public function openModal(ProdOrder $prodOrder, array $missingAssets): void
    {
        $this->prodOrder = $prodOrder;
        $this->missingAssets = $missingAssets;

        $this->content = json_encode($missingAssets, JSON_PRETTY_PRINT);

        $this->dispatch('open-modal', id: 'confirm-supply-modal');
    }

    public function confirmSupply(): void
    {
        try {
            app(ProdOrderService::class)->start($this->prodOrder);

            Notification::make()
                ->title('Success')
                ->body('Production order started successfully.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->dispatch('close-modal', id: 'confirm-supply-modal');
        }
    }

    public function cancelSupply(): void
    {
        $this->dispatch('close-modal', id: 'confirm-supply-modal');
    }

    public function render(): View
    {
        return view('livewire.confirm-supply-modal');
    }
}
