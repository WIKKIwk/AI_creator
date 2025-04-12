<?php

namespace App\Services;

use App\Enums\ProdOrderProductStatus;
use App\Enums\ProdOrderProductType;
use App\Enums\StepProductType;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use Exception;

class WorkStationService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {
    }

    /**
     * @throws Exception
     */
    public function completeWork(ProdOrderStep $prodOrderStep): void
    {
        if ($prodOrderStep->actualItems->isEmpty()) {
            throw new Exception('No actual items found for this step.');
        }

        foreach ($prodOrderStep->actualItems as $actualItem) {
            $this->transactionService->removeMiniStock(
                $actualItem->product_id,
                $actualItem->quantity,
                $prodOrderStep->work_station_id
            );
        }

        foreach ($prodOrderStep->expectedItems as $expectedItem) {
            $this->transactionService->addMiniStock(
                $expectedItem->product_id,
                $expectedItem->quantity,
                $prodOrderStep->work_station_id
            );
        }

        $prodOrderStep->update(['status' => ProdOrderProductStatus::Completed]);
        $prodOrderStep->workStation->update(['prod_order_id' => null]);
    }
}
