<?php

namespace App\Services;

use App\Enums\RoleType;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Enums\TaskAction;
use App\Events\SupplyOrderClosed;
use App\Models\ProdOrder\ProdOrder;
use App\Models\SupplyOrder\SupplyOrder;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SupplyOrderService
{
    public function __construct(
        protected ProdOrderService $prodOrderService,
        protected TransactionService $transactionService,
        protected TaskService $taskService
    ) {
    }

    /**
     * @throws Exception
     */
    public function createOrderByForm(array $data): SupplyOrder
    {
        $validator = Validator::make($data, [
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'supplier_organization_id' => 'required|exists:organizations,id',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.expected_quantity' => 'required|numeric|min:1',
            'products.*.price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            throw new Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        try {
            DB::beginTransaction();

            $supplyOrder = $this->storeOrder($data);
            foreach ($data['products'] as $productData) {
                $supplyOrder->products()->create($productData);
            }

            DB::commit();
            return $supplyOrder;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function storeForProdOrder(ProdOrder $prodOrder, $insufficientAssetsByCat): Collection
    {
        $result = collect();

        try {
            DB::beginTransaction();

            foreach ($insufficientAssetsByCat as $categoryId => $insufficientAssets) {

                /** @var SupplyOrder $existedSupplyOrder */
                $existedSupplyOrder = $prodOrder->supplyOrders()
                    ->where('product_category_id', $categoryId)
                    ->whereNull('closed_at')
                    ->exists();

                if ($existedSupplyOrder) {
                    continue;
                }

                $supplyOrder = $this->storeOrder([
                    'prod_order_id' => $prodOrder->id,
                    'warehouse_id' => $prodOrder->getWarehouseId(),
                    'product_category_id' => $categoryId,
                ]);

                foreach ($insufficientAssets as $productId => $insufficientAsset) {
                    $supplyOrder->products()->create([
                        'product_id' => $productId,
                        'expected_quantity' => $insufficientAsset['quantity'],
                        'actual_quantity' => 0,
                    ]);
                }

                $result->push($supplyOrder);
            }

            DB::commit();
            return $result;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception('Error creating supply order: ' . $e->getMessage());
        }
    }

    public function storeOrder(array $data): SupplyOrder
    {
        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->create([
            ...$data,
            'created_by' => auth()->user()->id,
        ]);
        $supplyOrder->updateStatus(SupplyOrderState::Created);

        return $supplyOrder;
    }

    /**
     * @throws Throwable
     */
    public function closeOrder(SupplyOrder $supplyOrder): void
    {
        $this->guardCloseOrder($supplyOrder);

        try {
            DB::beginTransaction();

            foreach ($supplyOrder->products as $product) {
                if ($product->actual_quantity == 0) {
                    throw new Exception("Product {$product->product->name} has 0 actual quantity");
                }

                $this->transactionService->addStock(
                    $product->product_id,
                    $product->actual_quantity,
                    $supplyOrder->warehouse_id,
                    $product->price
                );
            }

            $supplyOrder->setStatus(SupplyOrderState::Closed);
            $supplyOrder->closed_at = now();
            $supplyOrder->closed_by = auth()->user()->id;
            $supplyOrder->save();

            SupplyOrderClosed::dispatch($supplyOrder);

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function compareProducts(SupplyOrder $supplyOrder, array $products): void
    {
        try {
            DB::beginTransaction();

            $isProper = true;
            foreach ($supplyOrder->products as $product) {
                $productItem = Arr::first($products, fn($item) => $item['product_id'] == $product->product_id);
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
                TaskService::createTaskForRoles(
                    toUserRoles: [RoleType::SUPPLY_MANAGER->value],
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
     * @throws Exception
     */
    protected function guardCloseOrder(SupplyOrder $supplyOrder): void
    {
        if ($supplyOrder->closed_at) {
            throw new Exception('Supply order is already closed');
        }

        if (!$supplyOrder->supplier_organization_id) {
            throw new Exception('Supplier is not set');
        }

        if ($supplyOrder->products->isEmpty()) {
            throw new Exception('No products in supply order');
        }
    }
}
