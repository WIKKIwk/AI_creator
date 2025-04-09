<?php

namespace App\Services;

use App\Enums\ProdOrderProductStatus;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
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
