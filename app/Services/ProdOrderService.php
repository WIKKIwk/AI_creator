<?php

namespace App\Services;

use App\Enums\DurationUnit;
use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepProductStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\MiniInventory;
use App\Models\PerformanceRate;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\ProdTemplate\ProdTemplate;
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

        $insufficientAssetsByCat = [];
        foreach ($firstStep->materials as $item) {
            $requiredQuantity = $item->required_quantity * $prodOrder->quantity;
            $requiredQuantity = $this->transactionService->getMiniStockLackQty(
                $item->product_id,
                $requiredQuantity,
                $firstStep->work_station_id
            );

            $lackQuantity = $this->transactionService->getStockLackQty(
                $item->product_id,
                $requiredQuantity,
                $prodOrder->getWarehouseId()
            );

            // If there's still lack of quantity, create SupplyOrder and Block the ProdOrder
            if ($lackQuantity > 0) {
                $insufficientAssetsByCat[$item->product->category->id][$item->product_id] = $this->getInsufficientItemObj(
                    $item->product,
                    $lackQuantity
                );
            }
        }

        return $insufficientAssetsByCat;
    }

    /**
     * @throws Exception
     */
    public function start(ProdOrder $prodOrder): void
    {
        $this->guardAlreadyStarted($prodOrder);
        $this->guardCanBeProduced($prodOrder);

        $insufficientAssetsByCat = [];

        try {
            DB::beginTransaction();

            $firstStepId = null;
            $prodTemplate = $this->getTemplate($prodOrder->product_id);
            foreach ($prodTemplate->steps as $templateStep) {
                /** @var ProdOrderStep $poStep */
                $poStep = $prodOrder->steps()->create([
                    'sequence' => $templateStep->sequence,
                    'status' => ProdOrderStepStatus::InProgress,
                    'work_station_id' => $templateStep->work_station_id,
                    'output_product_id' => $templateStep->output_product_id,
                    'expected_quantity' => $templateStep->expected_quantity * $prodOrder->quantity,
                ]);

                foreach ($templateStep->materials as $item) {
                    $requiredQuantity = $item->required_quantity * $prodOrder->quantity;

                    $availableQuantity = 0;
                    if ($poStep->sequence == 1) {
                        $firstStepId = $poStep->id;

                        $lackMiniStockQty = $this->transactionService->removeMiniStockForce(
                            $item->product_id,
                            $requiredQuantity,
                            $poStep->work_station_id
                        );

                        $lackStockQty = $this->transactionService->removeStock(
                            $item->product_id,
                            $lackMiniStockQty,
                            $prodOrder->getWarehouseId(),
                            $poStep->work_station_id
                        );

                        $availableQuantity = $requiredQuantity - $lackStockQty;
                        // If there's still lack of quantity, create SupplyOrder and Block the ProdOrder
                        if ($lackStockQty > 0) {
                            $insufficientAssetsByCat[$item->product->category->id][$item->product_id] = $this->getInsufficientItemObj(
                                $item->product,
                                $lackStockQty
                            );
                        }
                    }

                    $poStep->materials()->create([
                        'status' => ProdOrderStepProductStatus::InProgress,
                        'product_id' => $item->product_id,
                        'required_quantity' => $requiredQuantity,
                        'available_quantity' => $availableQuantity,
                    ]);
                }
            }

            if (!empty($insufficientAssetsByCat)) {
                /** @var SupplyOrderService $supplyService */
                $supplyService = app(SupplyOrderService::class);
                $supplyService->storeForProdOrder($prodOrder, $insufficientAssetsByCat);
            }

            $prodOrder->current_step_id = $firstStepId;
            $prodOrder->status = !empty($insufficientAssetsByCat) ? OrderStatus::Blocked : OrderStatus::Processing;
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
    public function completeWork(ProdOrderStep $poStep, $outputQty): void
    {
        if ($poStep->status == ProdOrderStepStatus::Completed) {
            throw new Exception('Step is already completed');
        }

        if ($outputQty <= 0) {
            throw new Exception('Output quantity is not set');
        }

        try {
            DB::beginTransaction();

            /** @var Collection<ProdOrderStepProduct> $actualMaterials */
            $actualMaterials = $poStep->actualItems()->get();
            foreach ($actualMaterials as $actualMaterial) {
                $this->transactionService->removeMiniStock(
                    $actualMaterial->product_id,
                    $actualMaterial->required_quantity,
                    $poStep->work_station_id
                );
            }

            $this->transactionService->addMiniStock(
                $poStep->output_product_id,
                $outputQty,
                $poStep->work_station_id
            );

            $poStep->update([
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

                /*$nextStep->productItems()->create([
                    'product_id' => $currentStep->output_product_id,
                    'max_quantity' => $currentStep->output_quantity,
                    'quantity' => 0,
                    'type' => StepProductType::Actual,
                ]);*/

                $nextStep->workStation->update(['prod_order_id' => $prodOrder->id]);

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
                $prodOrder->getWarehouseId(),
                0,
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
     */
    public function checkMaterialsExact(ProdOrderStep $poStep, $productId, $quantity): array
    {
        return $this->checkMaterials($poStep, $productId, $quantity, false);
    }

    /**
     * @throws Exception
     */
    public function checkMaterials(ProdOrderStep $poStep, $productId, $quantity, $adding = true): array
    {
        $insufficientAssetsByCat = [];
        $existedMaterial = $this->getExistedMaterial($poStep, $productId);
        if ($adding) {
            $quantity += $existedMaterial->available_quantity;
        }

        $quantity = $this->transactionService->getMiniStockLackQty($productId, $quantity, $poStep->work_station_id);
        $lackQuantity = $this->transactionService->getStockLackQty(
            $productId,
            $quantity,
            $poStep->prodOrder->getWarehouseId()
        );

        // If there's still lack of quantity, stop iteration and return the insufficient assets
        if ($lackQuantity > 0) {
            /** @var Product $lackProduct */
            $lackProduct = Product::query()->find($productId);
            $insufficientAssetsByCat[$lackProduct->category->id][$lackProduct->id] = $this->getInsufficientItemObj(
                $lackProduct,
                $lackQuantity
            );
        }

        return $insufficientAssetsByCat;
    }

    /**
     * @throws Exception
     */
    public function changeMaterialAvailableExact(ProdOrderStep $poStep, $productId, $quantity): float
    {
        return $this->changeMaterialAvailable($poStep, $productId, $quantity, false);
    }

    /**
     * @throws Exception
     * TESTED
     */
    public function changeMaterialAvailable(ProdOrderStep $poStep, $productId, $quantity, $adding = true): float
    {
        $existedMaterial = $this->getExistedMaterial($poStep, $productId);
        if ($adding) {
            $quantity += $existedMaterial->available_quantity;
        }

        if ($quantity <= $existedMaterial->available_quantity) {
            $existedMaterial->update(['available_quantity' => $quantity]);
            return 0; // No lack of stock
        }

        $miniStock = $this->inventoryService->getMiniInventory($productId, $poStep->work_station_id);
        $lackMiniStock = max($quantity - $miniStock->quantity, 0);

        $lackStock = 0;
        if ($lackMiniStock > 0) {
            $lackStock = $this->transactionService->removeStock(
                $productId,
                $lackMiniStock,
                $poStep->prodOrder->getWarehouseId(),
                $poStep->work_station_id
            );
        }

        $takenStock = $lackMiniStock - $lackStock;
        if ($takenStock > 0) {
            $this->transactionService->addMiniStock($productId, $takenStock, $poStep->work_station_id);
            $existedMaterial->update([
                'available_quantity' => $existedMaterial->available_quantity + $takenStock,
            ]);
        }

        if ($lackStock > 0) {
            /** @var Product $targetProduct */
            $targetProduct = Product::query()->find($productId);
            $insufficientAssetsByCat[$targetProduct->category->id][$targetProduct->id] = $this->getInsufficientItemObj(
                $targetProduct,
                $lackStock
            );
            /** @var SupplyOrderService $supplyService */
            $supplyService = app(SupplyOrderService::class);
            $supplyService->storeForProdOrder($poStep->prodOrder, $insufficientAssetsByCat);
        }

        return $lackStock;
    }

    /**
     * @throws Exception
     */
    protected function getExistedMaterial(ProdOrderStep $poStep, $productId): ProdOrderStepProduct
    {
        /** @var ProdOrderStepProduct $existedMaterial */
        $existedMaterial = $poStep->materials()->where('product_id', $productId)->first();
        if (!$existedMaterial) {
            throw new Exception('Material not found in ProdOrderStep');
        }
        return $existedMaterial;
    }

    protected function getInsufficientItemObj(Product $product, $lackQuantity): array
    {
        return [
            'product' => $product,
            'quantity' => $lackQuantity,
            'category' => $product->category->name,
            'measure_unit' => $product->category->measure_unit->getLabel(),
        ];
    }

    /**
     * @throws Exception
     */
    protected function getTemplate($productId): ProdTemplate
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

    public function confirmOrderGroup(ProdOrderGroup $prodOrderGroup): void
    {
        foreach ($prodOrderGroup->prodOrders as $prodOrder) {
            $this->confirmOrder($prodOrder);
        }
    }

    public function confirmOrder(ProdOrder $prodOrder): void
    {
        if (!$prodOrder->confirmed_at) {
            $prodOrder->confirmed_at = now();
            $prodOrder->confirmed_by = auth()->user()->id;
            $prodOrder->save();
        }
    }

    public function getOrderGroupById($id): ?ProdOrderGroup
    {
        /** @var ProdOrderGroup $order */
        $order = ProdOrderGroup::query()->find($id);
        return $order;
    }

    public function calculateDeadline($productId): ?float
    {
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->where('product_id', $productId)->first();

        $totalDays = 0;
        foreach ($prodTemplate->steps as $step) {
            /** @var PerformanceRate $rate */
            $rate = $step->workStation->performanceRates()->where('product_id', $step->output_product_id)->first();
            if (!$rate) {
                continue;
            }

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

        return $totalDays;
    }

    public function calculateTotalCost($productId, $warehouseId): ?float
    {
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->where('product_id', $productId)->first();

        $totalCost = 0;
        foreach ($prodTemplate->steps as $step) {
            foreach ($step->materials as $material) {
                $inventory = $this->inventoryService->getInventory($material->product_id, $warehouseId);
                if ($inventory->unit_cost > 0) {
                    $totalCost += $inventory->unit_cost * $material->required_quantity;
                }
            }
        }

        return $totalCost;
    }

    /**
     * @throws Exception
     */
    protected function guardAlreadyStarted(ProdOrder $prodOrder): void
    {
        if ($prodOrder->started_at || $prodOrder->steps->isNotEmpty()) {
            throw new Exception('Order is already in processing');
        }
    }

    /**
     * @throws Exception
     */
    protected function guardCanBeProduced(ProdOrder $prodOrder): void
    {
        if (!$prodOrder->confirmed_at) {
            throw new Exception('ProdOrder is not confirmed yet');
        }
    }
}
