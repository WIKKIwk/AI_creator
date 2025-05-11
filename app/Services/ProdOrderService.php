<?php

namespace App\Services;

use App\Enums\DurationUnit;
use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\RoleType;
use App\Enums\StepProductType;
use App\Enums\SupplyOrderState;
use App\Enums\TaskAction;
use App\Models\InventoryItem;
use App\Models\MiniInventory;
use App\Models\PerformanceRate;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use App\Models\ProdTemplate;
use App\Models\Product;
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
                    'status' => ProdOrderStepStatus::InProgress,
                    'output_product_id' => $templateStep->output_product_id,
                    'expected_quantity' => $templateStep->expected_quantity * $prodOrder->quantity,
                ]);

                foreach ($templateStep->requiredItems as $item) {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'max_quantity' => $item->quantity * $prodOrder->quantity,
                        'quantity' => 0,
                        'type' => StepProductType::Required,
                    ]);

                    if ($prodOrderStep->sequence == 1) {
                        $firstStepId = $prodOrderStep->id;
                        $lackQuantity = $this->createActualItem(
                            $prodOrderStep,
                            $item->product_id,
                            $item->quantity * $prodOrder->quantity
                        );

                        // If there's still lack of quantity, create SupplyOrder and Block the ProdOrder
                        if ($lackQuantity > 0) {
                            app(SupplyOrderService::class)->storeForProdOrder($prodOrder, $item->product, $lackQuantity);
                            $insufficientAssets[$item->product_id] = [
                                'product' => $item->product,
                                'quantity' => $lackQuantity,
                                'measure_unit' => $item->product->category->measure_unit->getLabel(),
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

    public function checkMaterials(ProdOrderStep $prodOrderStep, $productId, $quantity): array
    {
        $prodOrder = $prodOrderStep->prodOrder;

        /** @var MiniInventory $miniStock */
        $miniStock = $prodOrderStep->workStation->miniInventories()
            ->where('product_id', $productId)
            ->first();

        $insufficientAssets = [];
        $diffQty = $quantity - $miniStock?->quantity;

        if ($diffQty <= 0) {
            return $insufficientAssets;
        }

        $lackQuantity = $this->transactionService->getStockLackQty(
            $productId,
            $diffQty,
            $prodOrder->warehouse_id
        );

        // If there's still lack of quantity, stop iteration and return the insufficient assets
        if ($lackQuantity > 0) {
            /** @var Product $lackProduct */
            $lackProduct = Product::query()->find($productId);
            if ($lackProduct) {
                $insufficientAssets[$lackProduct->id] = [
                    'product' => $lackProduct,
                    'quantity' => $lackQuantity,
                    'measure_unit' => $lackProduct->category->measure_unit->getLabel(),
                ];
            }
        }

        return $insufficientAssets;
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function editMaterials(ProdOrderStep $prodOrderStep, $productId, $quantity): void
    {
        /** @var Product|null $targetProduct */
        $targetProduct = Product::query()->find($productId);
        if (!$targetProduct) {
            throw new Exception('Product not found');
        }

        /** @var MiniInventory $miniStock */
        $miniStock = $prodOrderStep->workStation->miniInventories()
            ->where('product_id', $targetProduct->id)
            ->first();

        $diffQty = $quantity - $miniStock?->quantity;

        try {
            DB::beginTransaction();

            $prodOrder = $prodOrderStep->prodOrder;

            /** @var ProdOrderStepProduct $existingActualItem */
            $existingActualItem = $prodOrderStep->productItems()
                ->where('product_id', $targetProduct->id)
                ->where('type', StepProductType::Actual)
                ->first();

            $insufficientAssets = [];

            if ($diffQty > 0) {
                $lackQuantity = $this->transactionService->getStockLackQty(
                    $targetProduct->id,
                    $diffQty,
                    $prodOrder->warehouse_id
                );

                // If there's still lack of quantity, create SupplyOrder and Block the ProdOrder
                if ($lackQuantity > 0) {
                    app(SupplyOrderService::class)->storeForProdOrder($prodOrder, $targetProduct, $lackQuantity);
                    $insufficientAssets[$targetProduct->id] = [
                        'product' => $targetProduct,
                        'quantity' => $lackQuantity,
                        'measure_unit' => $targetProduct->category->measure_unit->getLabel(),
                    ];
                } else {
                    $this->transactionService->removeStock(
                        $targetProduct->id,
                        $diffQty,
                        $prodOrder->warehouse_id,
                        $prodOrderStep->work_station_id
                    );

                    $this->transactionService->addMiniStock($targetProduct->id, $diffQty, $prodOrderStep->work_station_id);
                }
            }

            if (empty($insufficientAssets)) {
                if ($existingActualItem) {
                    $existingActualItem->update([
                        'max_quantity' => $quantity,
                    ]);
                } else {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $targetProduct->id,
                        'max_quantity' => $quantity,
                        'quantity' => 0,
                        'type' => StepProductType::Actual,
                    ]);
                }
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
    public function completeWork(ProdOrderStep $prodOrderStep, $outputQty): void
    {
        if ($prodOrderStep->status == ProdOrderStepStatus::Completed) {
            throw new Exception('Step is already completed');
        }

        if ($outputQty <= 0) {
            throw new Exception('Output quantity is not set');
        }

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

            $this->transactionService->addMiniStock(
                $prodOrderStep->output_product_id,
                $outputQty,
                $prodOrderStep->work_station_id
            );

            $prodOrderStep->update([
                'status' => ProdOrderStepStatus::Completed,
                'output_quantity' => $outputQty,
            ]);

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
                $this->transactionService->removeMiniStock(
                    $currentStep->output_product_id,
                    $currentStep->output_quantity,
                    $currentStep->work_station_id
                );

                $this->transactionService->addMiniStock(
                    $currentStep->output_product_id,
                    $currentStep->output_quantity,
                    $nextStep->work_station_id
                );

                $nextStep->productItems()->create([
                    'product_id' => $currentStep->output_product_id,
                    'max_quantity' => $currentStep->output_quantity,
                    'quantity' => 0,
                    'type' => StepProductType::Actual,
                ]);

                $prodOrder->current_step_id = $nextStep->id;
            } else {
                // Order completed
                app(TaskService::class)->createTaskForRole(
                    toUserRole: RoleType::SENIOR_STOCK_MANAGER,
                    relatedType: ProdOrder::class,
                    relatedId: $prodOrder->id,
                    action: TaskAction::Approve,
                    comment: 'ProdOrder is completed and needs to be approved'
                );
                $prodOrder->status = OrderStatus::Completed;
            }

            $currentStep->workStation->update(['prod_order_id' => null]);
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

            $this->transactionService->removeMiniStock(
                $lastStep->output_product_id,
                $lastStep->output_quantity,
                $lastStep->work_station_id
            );

            $this->transactionService->addStock(
                $lastStep->output_product_id,
                $lastStep->output_quantity,
                0,
                $prodOrder->warehouse_id,
                workStationId: $lastStep->work_station_id,
            );

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
    public function createActualItem(ProdOrderStep $prodOrderStep, $productId, $quantity): int
    {
        $exist = $prodOrderStep->productItems()
            ->where('product_id', $productId)
            ->where('type', StepProductType::Actual)
            ->exists();

        if ($exist) {
            return 0;
        }

        $prodOrder = $prodOrderStep->prodOrder;

        $lackQuantity = $this->transactionService->removeStock(
            $productId,
            $quantity,
            $prodOrder->warehouse_id,
            $prodOrderStep->work_station_id
        );

        // Create ProdOrderStepProducts and add to WorkStation's mini Stock
        $takeQuantity = $quantity - $lackQuantity;

        if ($takeQuantity > 0) {
            $this->transactionService->addMiniStock(
                $productId,
                $takeQuantity,
                $prodOrderStep->work_station_id
            );

            $prodOrderStep->productItems()->create([
                'product_id' => $productId,
                'max_quantity' => $takeQuantity,
                'quantity' => 0,
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
            /** @var PerformanceRate $rate */
            $rate = $step->workStation->performanceRates()
                ->where('product_id', $step->output_product_id)
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

                $totalDays += ceil($step->expected_quantity / $quantityPerDay);
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
