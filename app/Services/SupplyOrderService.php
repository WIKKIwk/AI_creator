<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleType;
use App\Enums\StepProductType;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Enums\TaskAction;
use App\Models\SupplyOrder;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class SupplyOrderService
{
    public function __construct(
        protected ProdOrderService $prodOrderService,
        protected TransactionService $transactionService,
        protected TaskService $taskService
    ) {
    }

    public function store(array $values): void
    {
        try {
            DB::beginTransaction();

            /** @var SupplyOrder $supplyOrder */
            $supplyOrder = SupplyOrder::query()->create($values);
            $supplyOrder->updateStatus(SupplyOrderState::Created);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception('Error creating supply order: ' . $e->getMessage());
        }
    }

    public function compareProducts(SupplyOrder $supplyOrder, array $products): void
    {
        try {
            DB::beginTransaction();

            $isProper = true;
            foreach ($supplyOrder->products as $product) {
                $productItem = Arr::first($products, function ($item) use ($product) {
                    return $item['product_id'] == $product->product_id;
                });
                $actualQty = Arr::get($productItem, 'actual_quantity', 0);
                if ($actualQty != $product->expected_quantity) {
                    $isProper = false;
                }

                $product->actual_quantity = $actualQty;
                $product->save();
            }

            if ($isProper) {
                $this->closeOrder($supplyOrder);
            } else {
                $supplyOrder->updateStatus(
                    SupplyOrderState::Delivered,
                    SupplyOrderStatus::AwaitingSupplierApproval->value
                );
                $this->taskService->createTaskForRole(
                    toUserRole: RoleType::SUPPLY_MANAGER,
                    relatedType: SupplyOrder::class,
                    relatedId: $supplyOrder->id,
                    action: TaskAction::Check,
                    comment: 'Supply order compared. There are some differences in quantities.'
                );
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception('Error comparing products: ' . $e->getMessage());
        }
    }

    /**
     * @throws Throwable
     */
    public function closeOrder(SupplyOrder $supplyOrder): void
    {
        if ($supplyOrder->closed_at) {
            return;
        }

        if (!$supplyOrder->supplier_id) {
            throw new Exception('Supplier is not set');
        }

        try {
            DB::beginTransaction();

            foreach ($supplyOrder->products as $product) {
                $this->transactionService->addStock(
                    $product->product_id,
                    $product->actual_quantity,
                    $supplyOrder->total_price,
                    $supplyOrder->warehouse_id
                );
            }

            $prodOrder = $supplyOrder->prodOrder;
            if ($prodOrder && $prodOrder->status == OrderStatus::Blocked) {
                $prodOrder->update([
                    'status' => OrderStatus::Processing,
                    'confirmed_at' => now(),
                    'confirmed_by' => auth()->user()->id,
                ]);

                foreach ($prodOrder->currentStep->requiredItems as $requiredItem) {
                    $this->prodOrderService->createActualItem(
                        $prodOrder->currentStep,
                        $requiredItem->product_id,
                        $requiredItem->quantity,
                    );
                }
            }

            $supplyOrder->setStatus(SupplyOrderState::Closed);
            $supplyOrder->closed_at = now();
            $supplyOrder->closed_by = auth()->user()->id;
            $supplyOrder->save();

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
