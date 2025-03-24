<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StepProductType;
use App\Models\InventoryItem;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdTemplate;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProdOrderService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * @throws Exception
     */
    public function start(ProdOrder $prodOrder): void
    {
        $this->guardAlreadyStarted($prodOrder);

        try {
            DB::beginTransaction();

            $prodTemplate = $this->getTemplate($prodOrder->product_id);
            foreach ($prodTemplate->steps as $templateStep) {
                /** @var ProdOrderStep $prodOrderStep */
                $prodOrderStep = $prodOrder->steps()->create([
                    'prod_template_step_id' => $templateStep->id,
                    'work_station_id' => $templateStep->work_station_id,
                    'sequence' => $templateStep->sequence,
                    'status' => OrderStatus::Pending,
                ]);

                foreach ($templateStep->expectedItems as $item) {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity * $prodOrder->quantity,
                        'type' => StepProductType::Expected,
                    ]);
                }

                foreach ($templateStep->requiredItems as $item) {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity * $prodOrder->quantity,
                        'type' => StepProductType::Required,
                    ]);

                    if ($prodOrderStep->sequence == 1) {
                        $this->createActualItem(
                            $prodOrderStep,
                            $item->product_id,
                            $item->quantity * $prodOrder->quantity
                        );
                    }
                }
            }

            $prodOrder->status = OrderStatus::Processing;
            $prodOrder->current_step_id = $prodOrder->firstStep->id;
            $prodOrder->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function next(ProdOrder $prodOrder): void
    {
        try {
            $currentStep = $prodOrder->currentStep;

            /** @var ProdOrderStep $nextStep */
            $nextStep = $prodOrder->steps()
                ->where('sequence', '>', $currentStep->sequence)
                ->first();

            DB::beginTransaction();

            foreach ($currentStep->actualItems as $item) {
                $this->transactionService->removeMiniStock(
                    $item->product_id,
                    $item->quantity,
                    $currentStep->work_station_id
                );
            }

            foreach ($currentStep->expectedItems as $item) {
                $this->transactionService->addMiniStock(
                    $item->product_id,
                    $item->quantity,
                    null,
                    $nextStep?->work_station_id ?? $currentStep->work_station_id
                );

                $nextStep?->productItems()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'type' => StepProductType::Actual,
                ]);
            }

            if ($nextStep) {
                $prodOrder->current_step_id = $nextStep->id;
            } else {
                $prodOrder->status = OrderStatus::Completed;
            }
            $prodOrder->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function approve(ProdOrder $prodOrder): void
    {
        try {
            $this->transactionService->removeMiniStock(
                $prodOrder->product_id,
                $prodOrder->quantity,
                $prodOrder->currentStep->work_station_id
            );
            $this->transactionService->addStock(
                $prodOrder->product_id,
                $prodOrder->quantity,
                0,
                $prodOrder->warehouse_id,
                $prodOrder->currentStep->work_station_id
            );

            $prodOrder->status = OrderStatus::Approved;
            $prodOrder->save();
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function createActualItem(ProdOrderStep $prodOrderStep, $productId, $quantity): void
    {
        $this->transactionService->removeStock(
            $productId,
            $quantity,
            $prodOrderStep->prodOrder->warehouse_id,
            $prodOrderStep->work_station_id
        );

        $this->transactionService->addMiniStock($productId, $quantity, null, $prodOrderStep->work_station_id);

        $prodOrderStep->productItems()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'type' => StepProductType::Actual,
        ]);
    }

    public function calculateDeadline(ProdTemplate $prodTemplate): ?Carbon
    {
        return Carbon::now();
    }

    /**
     * @throws Exception
     */
    public function calculateTotalCost(ProdTemplate $prodTemplate): ?float
    {
        $totalCost = 0;

        return $totalCost;
    }

    /**
     * @throws Exception
     */
    public function guardAlreadyStarted(ProdOrder $prodOrder): void
    {
        if ($prodOrder->status == OrderStatus::Processing) {
            throw new Exception('Order is already in processing');
        }
    }

    /**
     * @throws Exception
     */
    public function getInventoryItem($productId, $storageLocationId = null, $storageFloor = null): InventoryItem
    {
        /** @var InventoryItem $inventoryItem */
        $inventoryItem = InventoryItem::query()
            ->where('product_id', $productId)
            ->when($storageLocationId, fn($query) => $query->where('storage_location_id', $storageLocationId))
            ->when($storageFloor, fn($query) => $query->where('storage_floor', $storageFloor))
            ->orderBy('created_at')
            ->first();

        if (!$inventoryItem) {
            throw new Exception('No inventory found for product');
        }

        return $inventoryItem;
    }

    /**
     * @throws Exception
     */
    public function getTemplate($productId): ProdTemplate
    {
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()
            ->where('product_id', $productId)
            ->latest()
            ->first();

        if (!$prodTemplate) {
            throw new Exception('No template found for product');
        }

        return $prodTemplate;
    }
}
