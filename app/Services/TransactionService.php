<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class TransactionService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {
    }

    /**
     * @throws Exception
     */
    public function addStock(
        $productId,
        $quantity,
        $cost = null,
        $warehouseId = null,
        $storageLocationId = null,
        $workStationId = null,
        $withTransaction = true
    ): void {
        $inventory = $this->inventoryService->getInventory($productId, $warehouseId);
        if ($cost) {
            $totalCost = $inventory->items->sum('quantity') * $inventory->unit_cost + $cost;
            $totalQuantity = $inventory->items->sum('quantity') + $quantity;
            $averageCost = $totalCost / $totalQuantity;

            $inventory->unit_cost = round($averageCost, 2);
            $inventory->save();
        }

        $inventoryItems = $this->inventoryService->getInventoryItems($inventory, $storageLocationId);
        if ($inventoryItems->isEmpty()) {
            throw new Exception('Insufficient stock');
        }

        /** @var InventoryItem $inventoryItem */
        $inventoryItem = $inventoryItems->first();
        $inventoryItem->quantity += $quantity;
        $inventoryItem->save();

        if ($withTransaction) {
            InventoryTransaction::query()->create([
                'product_id' => $inventory->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'storage_location_id' => $inventoryItem->storage_location_id,
                'work_station_id' => $workStationId,
                'quantity' => $quantity,
                'type' => TransactionType::In,
                'cost' => $cost,
            ]);
        }
    }

    public function addMiniStock(
        $productId,
        $quantity,
        $workStationId = null,
        $cost = null
    ): void {
        $miniInventory = $this->inventoryService->getMiniInventory($productId, $workStationId);

        if ($cost) {
            $totalCost = $miniInventory->quantity * $miniInventory->unit_cost + $cost;
            $totalQuantity = $miniInventory->quantity + $quantity;
            $averageCost = $totalCost / $totalQuantity;

            $miniInventory->unit_cost = round($averageCost, 2);
        }

        $miniInventory->quantity += $quantity;
        $miniInventory->save();
    }

    /**
     * @throws Exception
     */
    public function removeMiniStock($productId, $quantity, $workStationId): void
    {
        $miniInventory = $this->inventoryService->getMiniInventory($productId, $workStationId);

        if ($miniInventory->quantity < $quantity) {
            throw new Exception(
                "Insufficient quantity. Product: " . $miniInventory->product->name . ". Actual quantity: " . $miniInventory->quantity
            );
        }

        $miniInventory->quantity -= $quantity;
        $miniInventory->save();
    }
}
