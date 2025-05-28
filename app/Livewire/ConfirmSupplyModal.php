<?php

namespace App\Livewire;

use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Services\ProdOrderService;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class ConfirmSupplyModal extends Component
{
    public ProdOrder $prodOrder;
    public $arguments = [];
    public $missingAssetsByCat = [];
    public $action = null;

    protected $listeners = ['openModal'];

    public function openModal(ProdOrder $prodOrder, array $missingAssetsByCat, $action = null, $arguments = []): void
    {
        $this->prodOrder = $prodOrder;
        $this->missingAssetsByCat = $missingAssetsByCat;
        $this->arguments = $arguments;
        $this->action = $action;

        $this->dispatch('open-modal', id: 'confirm-supply-modal');
    }

    public function confirmSupply(): void
    {
        /** @var ProdOrderService $prodOrderService */
        $prodOrderService = app(ProdOrderService::class);

        try {
            if ($this->action == 'startProdOrder') {
                $prodOrderService->start($this->prodOrder);
                showSuccess('Production order started successfully');
            } elseif ($this->action === 'editMaterials') {
                [$stepId, $productId, $qty] = $this->arguments;

                /** @var ProdOrderStep $step */
                $step = ProdOrderStep::query()->find($stepId);
                $prodOrderService->changeMaterialAvailable($step, $productId, $qty);
//                showSuccess('Material edited successfully');
            }
        } catch (Throwable $e) {
            showError($e->getMessage());
        } finally {
            $this->dispatch('close-modal', id: 'confirm-supply-modal');
            $this->dispatch('refresh-page');
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
