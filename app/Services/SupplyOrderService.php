<?php

namespace App\Services;

use App\Enums\RoleType;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Enums\TaskAction;
use App\Events\SupplyOrderChanged;
use App\Events\SupplyOrderClosed;
use App\Listeners\ProdOrderNotification;
use App\Listeners\SupplyOrderNotification;
use App\Models\ProdOrder\ProdOrder;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

                SupplyOrderChanged::dispatch($supplyOrder);
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

    public function notifyCompareProducts(SupplyOrder $supplyOrder): void
    {
        /** @var Collection<User> $stockManagers */
        $stockManagers = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->where('warehouse_id', $supplyOrder->warehouse_id)
            ->whereIn('role', [RoleType::STOCK_MANAGER, RoleType::SENIOR_STOCK_MANAGER])
            ->get();

        $message = "<b>SupplyOrder waiting for StockManager approval</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder, false);

        foreach ($stockManagers as $stockManager) {
            TelegramService::sendMessage($stockManager->chat_id, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => 'Compare products', 'callback_data' => "compareSupplyOrder:$supplyOrder->id"]]
                ]),
            ]);
        }

        TaskService::createTaskForRoles(
            toUserRoles: [RoleType::SENIOR_STOCK_MANAGER->value, RoleType::STOCK_MANAGER->value],
            relatedType: SupplyOrder::class,
            relatedId: $supplyOrder->id,
            action: TaskAction::Check,
            comment: 'Supply order delivered. Need to compare products.'
        );
    }

    public function notifyCompareProductsDiff(SupplyOrder $supplyOrder): void
    {
        /** @var Collection<User> $supplyManagers */
        $supplyManagers = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->whereIn('role', [RoleType::SENIOR_SUPPLY_MANAGER, RoleType::SUPPLY_MANAGER])
            ->get();

        $message = "<b>SupplyOrder products comparison</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);
        $message .= "\nThere are some differences in quantities.";

        foreach ($supplyManagers as $supplyManager) {
            TelegramService::sendMessage($supplyManager->chat_id, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => 'Close order', 'callback_data' => "closeSupplyOrder:$supplyOrder->id"]]
                ]),
            ]);
        }

        TaskService::createTaskForRoles(
            toUserRoles: [RoleType::SENIOR_SUPPLY_MANAGER->value, RoleType::SUPPLY_MANAGER->value],
            relatedType: SupplyOrder::class,
            relatedId: $supplyOrder->id,
            action: TaskAction::Check,
            comment: 'Supply order compared. There are some differences in quantities.'
        );
    }

    public function notifyClosedAfterCompare(SupplyOrder $supplyOrder): void
    {
        /** @var Collection<User> $supplyManagers */
        $supplyManagers = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->whereIn('role', [RoleType::SENIOR_SUPPLY_MANAGER, RoleType::SUPPLY_MANAGER])
            ->get();

        $message = "<b>SupplyOrder closed by Stock Manager</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        foreach ($supplyManagers as $supplyManager) {
            TelegramService::sendMessage($supplyManager->chat_id, $message, ['parse_mode' => 'HTML']);
        }

        TaskService::createTaskForRoles(
            toUserRoles: [RoleType::SENIOR_SUPPLY_MANAGER->value, RoleType::SUPPLY_MANAGER->value],
            relatedType: SupplyOrder::class,
            relatedId: $supplyOrder->id,
            action: TaskAction::Check,
            comment: 'Supply order closed after products comparison. All quantities are correct.'
        );
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
                $this->notifyClosedAfterCompare($supplyOrder);
            } else {
                $supplyOrder->updateStatus(
                    SupplyOrderState::Delivered,
                    SupplyOrderStatus::AwaitingSupplierApproval->value
                );
                $this->notifyCompareProductsDiff($supplyOrder);
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

        if (!$supplyOrder->supplier_id) {
            throw new Exception('Supplier is not set');
        }

        if ($supplyOrder->products->isEmpty()) {
            throw new Exception('No products in supply order');
        }
    }
}
