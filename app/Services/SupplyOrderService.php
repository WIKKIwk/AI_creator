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

            $transaction = InventoryTransaction::query()->create([
                'type' => TransactionType::In,
                'product_id' => $order->product_id,
                'quantity' => $order->quantity,
                'cost' => $order->total_price,
                'warehouse_id' => $order->warehouse_id,
                'supplier_id' => $order->supplier_id,
            ]);

            $order->status = OrderStatus::Completed;
            $order->save();

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
