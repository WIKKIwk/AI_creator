<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StepProductType;
use App\Enums\TransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdTemplate;
use App\Models\SupplyOrder;
use Exception;
use App\Models\ProdOrderStepProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProdOrderService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected InventoryService $inventoryService,
    ) {
    }

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
        $prodOrder = $prodOrderStep->prodOrder;

        $inventory = $this->inventoryService->getInventory($productId, $prodOrder->warehouse_id);
        /** @var Collection<InventoryItem> $inventoryItems */
        $inventoryItems = $this->inventoryService->getInventoryItems($inventory);

        $lackQuantity = $quantity;
        foreach ($inventoryItems as $inventoryItem) {
            if ($lackQuantity <= 0) {
                break;
            }

            $quantityOut = min($inventoryItem->quantity, $lackQuantity);
            $inventoryItem->quantity -= $quantityOut;
            $lackQuantity -= $quantityOut;
            $inventoryItem->save();

            InventoryTransaction::query()->create([
                'product_id' => $inventory->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'storage_location_id' => $inventoryItem->storage_location_id,
                'work_station_id' => $prodOrderStep->work_station_id,
                'quantity' => $quantityOut,
                'type' => TransactionType::Out,
                'cost' => $inventory->unit_cost,
            ]);
        }

        if ($lackQuantity > 0) {
            SupplyOrder::query()->create([
                'warehouse_id' => $prodOrder->warehouse_id,
                'product_id' => $productId,
                'quantity' => $lackQuantity,
                'status' => OrderStatus::Pending,
            ]);
        }

        $takenQuantity = $quantity - $lackQuantity;

        $this->transactionService->addMiniStock(
            $productId,
            $takenQuantity,
            null,
            $prodOrderStep->work_station_id
        );

        /** @var ProdOrderStepProduct $existingStepItem */
        $existingStepItem = $prodOrderStep->productItems()
            ->where('product_id', $productId)
            ->where('type', StepProductType::Actual)
            ->first();

        if ($existingStepItem) {
            $existingStepItem->quantity += $takenQuantity;
            $existingStepItem->save();
        } else {
            $prodOrderStep->productItems()->create([
                'product_id' => $productId,
                'quantity' => $takenQuantity,
                'type' => StepProductType::Actual,
            ]);
        }
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
