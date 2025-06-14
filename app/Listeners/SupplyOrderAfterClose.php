<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\SupplyOrderClosed;
use App\Services\ProdOrderService;
use Exception;

class SupplyOrderAfterClose
{
    /**
     * Create the event listener.
     */
    public function __construct(protected ProdOrderService $prodOrderService)
    {
        //
    }

    /**
     * Handle the event.
     * @throws Exception
     */
    public function handle(SupplyOrderClosed $event): void
    {
        $prodOrder = $event->supplyOrder->prodOrder;
        if ($prodOrder && $prodOrder->status == OrderStatus::Blocked) {
            $prodOrder->update(['status' => OrderStatus::Processing]);

            foreach ($prodOrder->firstStep->materials as $material) {
                $this->prodOrderService->updateMaterialAvailableExact(
                    $prodOrder->firstStep,
                    $material->product_id,
                    $material->required_quantity,
                );
            }

            $this->prodOrderService->notifyProdOrderReady($prodOrder);
        }
    }
}
