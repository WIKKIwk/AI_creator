<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\InventoryTransaction;
use App\Models\SupplyOrder;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class SupplyOrderService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {
    }

    /**
     * @throws Throwable
     */
    public function completeOrder(SupplyOrder $order): void
    {
        if ($order->status == OrderStatus::Completed) {
            return;
        }

        if (!$order->supplier_id) {
            throw new Exception('Supplier is not set');
        }

        try {
            DB::beginTransaction();

            $this->transactionService->addStock(
                $order->product_id,
                $order->quantity,
                $order->total_price,
                $order->warehouse_id
            );

            $order->status = OrderStatus::Completed;
            $order->save();

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
