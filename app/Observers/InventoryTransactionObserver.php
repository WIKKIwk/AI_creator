<?php

namespace App\Observers;

use App\Enums\TransactionType;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Exception;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryTransactionObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * @throws Throwable
     */
    public function creating(InventoryTransaction $transaction): void
    {
        try {
            DB::beginTransaction();

            $inventory = $this->getInventory($transaction);
            $inventoryItems = $inventory->items;
            $properInventoryItem = $this->getInventoryItem($inventory, $transaction);

            if ($transaction->type === TransactionType::In) {
                $totalCost = $inventoryItems->sum('quantity') * $inventory->unit_cost + $transaction->cost;
                $totalQuantity = $inventoryItems->sum('quantity') + $transaction->quantity;
                $averageCost = $totalCost / $totalQuantity;

                $properInventoryItem->quantity += $transaction->quantity;
                $inventory->unit_cost = round($averageCost, 2);
            } elseif ($transaction->type === TransactionType::Out) {
                if ($properInventoryItem->quantity < $transaction->quantity) {
                    throw new Exception('Insufficient quantity');
                }
                $properInventoryItem->quantity -= $transaction->quantity;
            }

            $properInventoryItem->save();
            $inventory->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function getInventory(InventoryTransaction $transaction): Inventory
    {
        $inventory = Inventory::query()
            ->where('product_id', $transaction->product_id)
            ->where('warehouse_id', $transaction->warehouse_id)
            ->first();

        if (!$inventory) {
            $inventory = Inventory::query()->create([
                'product_id' => $transaction->product_id,
                'warehouse_id' => $transaction->warehouse_id,
                'quantity' => 0,
                'unit_cost' => 0,
            ]);
        }

        return $inventory;
    }

    protected function getInventoryItem(Inventory $inventory, InventoryTransaction $transaction): InventoryItem
    {
        $properInventoryItem = $inventory->items->first(function ($item) use ($transaction) {
            if (!$transaction->storage_location_id) {
                return $item->storage_location_id === null;
            }
            return $item->storage_location_id === $transaction->storage_location_id;
        });

        if (!$properInventoryItem) {
            $properInventoryItem = new InventoryItem([
                'inventory_id' => $inventory->id,
                'quantity' => 0,
                'storage_location_id' => $transaction->storage_location_id,
            ]);
        }

        return $properInventoryItem;
    }
}
