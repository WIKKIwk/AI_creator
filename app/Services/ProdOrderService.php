<?php

namespace App\Services;

use App\Enums\DurationUnit;
use App\Enums\OrderStatus;
use App\Enums\ProdOrderGroupType;
use App\Enums\ProdOrderStepProductStatus;
use App\Enums\ProdOrderStepStatus;
use App\Models\PerformanceRate;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

        // Now we need to increase available quantity
        $requiredAdditionalQty = $quantity - $existedMaterial->available_quantity;

        $miniStock = $this->inventoryService->getMiniInventory($productId, $poStep->work_station_id);

        // Free mini_stock = total - already used
        $freeMiniStock = $miniStock->quantity - $existedMaterial->available_quantity;

        // Take from free mini_stock first
        $fromMiniStock = min($requiredAdditionalQty, max($freeMiniStock, 0));
        $fromStock = $requiredAdditionalQty - $fromMiniStock;

        $lackStock = 0;
        $takenFromMain = 0;
        if ($fromStock > 0) {
            $lackStock = $this->transactionService->removeStock(
                $productId,
                $fromStock,
                $poStep->prodOrder->getWarehouseId(),
                $poStep->work_station_id
            );

            $takenFromMain = $fromStock - $lackStock;
            if ($takenFromMain > 0) {
                $this->transactionService->addMiniStock($productId, $takenFromMain, $poStep->work_station_id);
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
        }

        // Update material
        $existedMaterial->update([
            'available_quantity' => $existedMaterial->available_quantity + $fromMiniStock + $takenFromMain,
        ]);

        return $lackStock;
    }

    /**
     * @throws Exception
     */
    public function createProdOrderByForm(array $data): ProdOrderGroup
    {
        $validator = Validator::make($data, [
            'type' => 'required|in:' . ProdOrderGroupType::ByOrder->value . ',' . ProdOrderGroupType::ByCatalog->value,
            'warehouse_id' => 'required|exists:warehouses,id',
            'organization_id' => 'nullable|exists:organizations,id',
            'deadline' => 'nullable|date|after_or_equal:today',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.offer_price' => 'numeric',
        ]);

        if ($validator->fails()) {
            throw new Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        try {
            DB::beginTransaction();

            $warehouseId = $data['warehouse_id'];

            /** @var ProdOrderGroup $prodOrderGroup */
            $prodOrderGroup = ProdOrderGroup::query()->create([
                'type' => $data['type'],
                'warehouse_id' => $warehouseId,
                'organization_id' => $data['organization_id'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'created_by' => auth()->user()->id,
            ]);

            $products = $data['products'] ?? [];
            foreach ($products as $productData) {
                $productId = $productData['product_id'];

                /** @var ProdOrder $prodOrder */
                $prodOrderGroup->prodOrders()->create([
                    'product_id' => $productId,
                    'quantity' => $productData['quantity'],
                    'offer_price' => $productData['offer_price'] ?? 0,
                    'status' => OrderStatus::Pending,
                    'deadline' => $this->calculateDeadline($productId),
                    'total_cost' => $this->calculateTotalCost($productId, $warehouseId)
                ]);
            }

            DB::commit();
            return $prodOrderGroup;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function createExecutionByForm(ProdOrderStep $poStep, array $data): void
    {
        $materials = $data['materials'] ?? [];
        $outputQuantity = $data['output_quantity'] ?? 0;

        try {
            DB::beginTransaction();

            /** @var ProdOrderStepExecution $execution */
            $execution = $poStep->executions()->create([
                'output_quantity' => $outputQuantity,
                'notes' => $data['notes'] ?? '',
                'executed_by' => auth()->user()->id,
            ]);

            foreach ($materials as $material) {
                $productId = $material['product_id'] ?? null;
                $usedQuantity = $material['used_quantity'] ?? 0;

                if (!$productId || $usedQuantity <= 0) {
                    throw new Exception('Invalid material data provided');
                }

                $poMaterial = $this->getExistedMaterial($poStep, $productId);
                if ($poMaterial->available_quantity < $usedQuantity) {
                    $product = Product::query()->find($productId);
                    throw new Exception('Insufficient available quantity for product: ' . $product->catName);
                }

                // Create execution material record
                $execution->materials()->create([
                    'product_id' => $productId,
                    'used_quantity' => $usedQuantity,
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
     */
    public function approveExecution(ProdOrderStepExecution $execution): void
    {
        if ($execution->approved_at) {
            throw new Exception('Execution is already approved');
        }

        try {
            DB::beginTransaction();

            $poStep = $execution->prodOrderStep;
            foreach ($execution->materials as $execMaterial) {
                $this->useMaterial($poStep, $execMaterial->product_id, $execMaterial->used_quantity);
            }

            $this->outputMaterial($poStep, $execution->output_quantity);

            $execution->update([
                'approved_at' => now(),
                'approved_by' => auth()->user()->id
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    protected function outputMaterial(ProdOrderStep $currentStep, $outputQty): void
    {
        /** @var ProdOrderStep $nextStep */
        $nextStep = $currentStep->prodOrder->steps()->where('sequence', '>', $currentStep->sequence)->first();
        if ($nextStep) {
            $nextMiniStock = $this->transactionService->addMiniStock(
                $currentStep->output_product_id,
                $outputQty,
                $nextStep->work_station_id
            );
        } else {
            $this->transactionService->addStock(
                $currentStep->output_product_id,
                $outputQty,
                $currentStep->prodOrder->getWarehouseId(),
                workStationId: $currentStep->work_station_id
            );
        }

        $totalOutputQty = $currentStep->output_quantity + $outputQty;
        if ($totalOutputQty >= $currentStep->expected_quantity) {
            $currentStep->status = ProdOrderStepStatus::Completed;
        } else {
            $currentStep->status = ProdOrderStepStatus::InProgress;
        }

        $currentStep->output_quantity = $totalOutputQty;
        $currentStep->save();
    }

    /**
     * @throws Exception
     */
    protected function useMaterial(ProdOrderStep $poStep, $productId, $usedQty): void
    {
        $poMaterial = $this->getExistedMaterial($poStep, $productId);
        if ($poMaterial->available_quantity < $usedQty) {
            throw new Exception('Not enough available quantity');
        }

        $poMaterial->update([
            'available_quantity' => $poMaterial->available_quantity - $usedQty,
            'used_quantity' => $poMaterial->used_quantity + $usedQty,
        ]);

        $this->transactionService->removeMiniStock(
            $poMaterial->product_id,
            $usedQty,
            $poMaterial->step->work_station_id
        );
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
        if (!$prodTemplate) {
            return null;
        }

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
        if (!$prodTemplate) {
            return null;
        }

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
