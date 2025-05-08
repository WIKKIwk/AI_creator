<?php

namespace App\Services;

use App\Enums\DurationUnit;
use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\StepProductType;
use App\Models\InventoryItem;
use App\Models\MiniInventory;
use App\Models\PerformanceRate;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use App\Models\ProdTemplate;
use App\Models\SupplyOrder;
use Exception;
use Illuminate\Database\Eloquent\Collection;
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
    public function checkStart(ProdOrder $prodOrder): array
    {
        $prodTemplate = $this->getTemplate($prodOrder->product_id);
        /** @var ProdOrderStep $firstStep */
        $firstStep = $prodTemplate->steps()->first();

        $insufficientAssets = [];
        foreach ($firstStep->requiredItems as $item) {
            $lackQuantity = $this->transactionService->getStockLackQty(
                $item->product_id,
                $item->quantity * $prodOrder->quantity,
                $prodOrder->warehouse_id
            );

            // If there's still lack of quantity, create SupplyOrder and Block the ProdOrder
            if ($lackQuantity > 0) {
                $insufficientAssets[$item->product_id] = [
                    'product' => $item->product,
                    'quantity' => $lackQuantity,
                    'measure_unit' => $item->product->category->measure_unit->getLabel(),
                ];
            }
        }

        return $insufficientAssets;
    }

    /**
     * @throws Exception
     */
    public function start(ProdOrder $prodOrder): void
    {
        $this->guardAlreadyStarted($prodOrder);
        $this->guardCanBeProduced($prodOrder);

        $insufficientAssets = [];

        try {
            DB::beginTransaction();

            $firstStepId = null;
            $prodTemplate = $this->getTemplate($prodOrder->product_id);
            foreach ($prodTemplate->steps as $templateStep) {
                /** @var ProdOrderStep $prodOrderStep */
                $prodOrderStep = $prodOrder->steps()->create([
                    'work_station_id' => $templateStep->work_station_id,
                    'sequence' => $templateStep->sequence,
                    'status' => OrderStatus::Pending,
                    'output_product_id' => $templateStep->output_product_id,
                    'expected_quantity' => $templateStep->expected_quantity,
                ]);

                foreach ($templateStep->requiredItems as $item) {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity * $prodOrder->quantity,
                        'type' => StepProductType::Required,
                    ]);

                    if ($prodOrderStep->sequence == 1) {
                        $firstStepId = $prodOrderStep->id;
                        $lackQuantity = $this->createFirstActualItems(
                            $prodOrderStep,
                            $item->product_id,
                            $item->quantity * $prodOrder->quantity
                        );

                        // If there's still lack of quantity, create SupplyOrder and Block the ProdOrder
                        if ($lackQuantity > 0) {
                            SupplyOrder::query()->create([
                                'prod_order_id' => $prodOrder->id,
                                'warehouse_id' => $prodOrder->warehouse_id,
                                'product_id' => $item->product_id,
                                'quantity' => $lackQuantity,
                                'status' => OrderStatus::Pending,
                                'created_by' => auth()->user()->id,
                            ]);
                            $insufficientAssets[$item->product_id] = [
                                'product' => $item->product,
                                'quantity' => $lackQuantity,
                            ];
                        }
                    }
                }
            }

            $prodOrder->current_step_id = $firstStepId;
            $prodOrder->status = !empty($insufficientAssets) ? OrderStatus::Blocked : OrderStatus::Processing;
            $prodOrder->started_at = now();
            $prodOrder->started_by = auth()->user()->id;
            $prodOrder->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function editMaterials(ProdOrderStep $prodOrderStep, $productId, $quantity): void
    {
        try {
            DB::beginTransaction();

            $prodOrder = $prodOrderStep->prodOrder;

            /** @var ProdOrderStepProduct $existingActualItem */
            $existingActualItem = $prodOrderStep->productItems()
                ->where('product_id', $productId)
                ->where('type', StepProductType::Actual)
                ->first();

            /** @var MiniInventory $miniStock */
            $miniStock = $prodOrderStep->workStation->miniInventories()
                ->where('product_id', $productId)
                ->first();

            $diffQty = $quantity - $miniStock?->quantity;

            if ($diffQty > 0) {
                if (!$this->transactionService->checkStock($productId, $diffQty, $prodOrder->warehouse_id)) {
                    throw new Exception('Insufficient stock');
                }

                $this->transactionService->removeStock(
                    $productId,
                    $diffQty,
                    $prodOrder->warehouse_id,
                    $prodOrderStep->work_station_id
                );

                $this->transactionService->addMiniStock($productId, $diffQty, $prodOrderStep->work_station_id);
            }

            if ($existingActualItem) {
                $existingActualItem->update([
                    'quantity' => $quantity,
                    'max_quantity' => $quantity,
                ]);
            } else {
                $prodOrderStep->productItems()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'max_quantity' => $quantity,
                    'type' => StepProductType::Actual,
                ]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function completeWork(ProdOrderStep $prodOrderStep): void
    {
        try {
            DB::beginTransaction();

            /** @var Collection<ProdOrderStepProduct> $actualMaterials */
            $actualMaterials = $prodOrderStep->actualItems()->get();
            foreach ($actualMaterials as $actualMaterial) {
                $this->transactionService->removeMiniStock(
                    $actualMaterial->product_id,
                    $actualMaterial->quantity,
                    $prodOrderStep->work_station_id
                );
            }

            //            /** @var Collection<ProdOrderStepProduct> $expectedMaterials */
            //            $expectedMaterials = $prodOrderStep->expectedItems()->get();
            //            foreach ($expectedMaterials as $expectedMaterial) {
            //                $this->transactionService->addMiniStock(
            //                    $expectedMaterial->product_id,
            //                    $expectedMaterial->quantity,
            //                    $prodOrderStep->work_station_id
            //                );
            //            }

            $prodOrderStep->update(['status' => ProdOrderStepStatus::Completed]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function next(ProdOrder $prodOrder): ?ProdOrderStep
    {
        $currentStep = $prodOrder->currentStep;
        if ($currentStep->status != ProdOrderStepStatus::Completed) {
            throw new Exception('Current step is not completed');
        }

        /** @var ProdOrderStep $nextStep */
        $nextStep = $prodOrder->steps()
            ->where('sequence', '>', $currentStep->sequence)
            ->first();

        try {
            DB::beginTransaction();

            if ($nextStep) {
                foreach ($currentStep->expectedItems as $item) {
                    $this->transactionService->removeMiniStock(
                        $item->product_id,
                        $item->quantity,
                        $currentStep->work_station_id
                    );

                    $this->transactionService->addMiniStock(
                        $item->product_id,
                        $item->quantity,
                        $nextStep->work_station_id
                    );

                    $nextStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'type' => StepProductType::Actual,
                    ]);
                }

                $prodOrder->current_step_id = $nextStep->id;
            } else {
                $prodOrder->status = OrderStatus::Completed;
            }

            $prodOrder->save();

            DB::commit();
            return $nextStep;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function approve(ProdOrder $prodOrder): void
    {
        $lastStep = $prodOrder->lastStep;
        if ($prodOrder->status != OrderStatus::Completed) {
            throw new Exception('ProdOrder is not completed yet');
        }

        try {
            DB::beginTransaction();

            foreach ($lastStep->expectedItems as $expectedItem) {
                $this->transactionService->removeMiniStock(
                    $expectedItem->product_id,
                    $expectedItem->quantity,
                    $lastStep->work_station_id
                );

                $this->transactionService->addStock(
                    $expectedItem->product_id,
                    $expectedItem->quantity,
                    0,
                    $prodOrder->warehouse_id,
                    workStationId: $lastStep->work_station_id,
                );
            }

            $prodOrder->status = OrderStatus::Approved;
            $prodOrder->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function createFirstActualItems(ProdOrderStep $prodOrderStep, $productId, $quantity): int
    {
        $prodOrder = $prodOrderStep->prodOrder;

        $lackQuantity = $this->transactionService->removeStock(
            $productId,
            $quantity,
            $prodOrder->warehouse_id,
            $prodOrderStep->work_station_id
        );

        // Create ProdOrderStepProducts and add to WorkStation's mini Stock
        $takenQuantity = $quantity - $lackQuantity;

        if ($takenQuantity > 0) {
            $this->transactionService->addMiniStock(
                $productId,
                $takenQuantity,
                $prodOrderStep->work_station_id
            );

            $prodOrderStep->productItems()->create([
                'product_id' => $productId,
                'quantity' => $takenQuantity,
                'type' => StepProductType::Actual,
            ]);
        }

        return $lackQuantity;
    }

    public function calculateDeadline($productId): ?float
    {
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->where('product_id', $productId)->first();

        $totalDays = 0;
        foreach ($prodTemplate->steps as $step) {
            foreach ($step->expectedItems as $expectedItem) {
                /** @var PerformanceRate $rate */
                $rate = $step->workStation->performanceRates()
                    ->where('product_id', $expectedItem->product_id)
                    ->first();

                if ($rate) {
                    $quantityPerUnit = $rate->quantity / $rate->duration;

                    $quantityPerDay = match ($rate->duration_unit) {
                        DurationUnit::Hour => $quantityPerUnit * 12,
                        DurationUnit::Day => $quantityPerUnit,
                        DurationUnit::Week => $quantityPerUnit / 7,
                        DurationUnit::Month => $quantityPerUnit / 30,
                        DurationUnit::Year => $quantityPerUnit / 365,
                    };

                    $totalDays += ceil($expectedItem->quantity / $quantityPerDay);
                }
            }
        }

        return $totalDays;
    }

    public function calculateTotalCost($productId, $warehouseId): ?float
    {
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->where('product_id', $productId)->first();

        $totalCost = 0;
        foreach ($prodTemplate->steps as $step) {
            foreach ($step->requiredItems as $requiredItem) {
                $inventory = $this->inventoryService->getInventory($requiredItem->product_id, $warehouseId);
                if ($inventory->unit_cost > 0) {
                    $totalCost += $inventory->unit_cost * $requiredItem->quantity;
                }
            }
        }

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
    public function guardCanBeProduced(ProdOrder $prodOrder): void
    {
        if (!$prodOrder->confirmed_at) {
            throw new Exception('ProdOrder is not confirmed yet');
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
