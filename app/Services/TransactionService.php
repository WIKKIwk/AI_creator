<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\InventoryTransaction;
use Exception;

class TransactionService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {
    }

    public function addStockByTransaction(InventoryTransaction $transaction): void
    {
        $inventory = $this->inventoryService->getInventory($transaction->product_id, $transaction->warehouse_id);
        $properInventoryItem = $this->inventoryService->getInventoryItem($inventory, $transaction->storage_location_id);

        if ($transaction->cost) {
            $totalCost = $inventory->items->sum('quantity') * $inventory->unit_cost + $transaction->cost;
            $totalQuantity = $inventory->items->sum('quantity') + $transaction->quantity;
            $averageCost = $totalCost / $totalQuantity;

            $inventory->unit_cost = round($averageCost, 2);
            $inventory->save();
        }

        $properInventoryItem->quantity += $transaction->quantity;
        $properInventoryItem->save();
    }

    public function addStock(
        $productId,
        $quantity,
        $cost = null,
        $warehouseId = null,
        $workStationId = null,
        $storageLocationId = null
    ): void {
        InventoryTransaction::query()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'cost' => $cost ?? 0,
            'warehouse_id' => $warehouseId,
            'work_station_id' => $workStationId,
            'storage_location_id' => $storageLocationId,
            'type' => TransactionType::In,
        ]);
    }

    public function addMiniStock(
        $productId,
        $quantity,
        $cost = null,
        $workStationId = null
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
    public function removeStockByTransaction(InventoryTransaction $transaction): void
    {
        $inventory = $this->inventoryService->getInventory($transaction->product_id, $transaction->warehouse_id);
        $properInventoryItem = $this->inventoryService->getInventoryItem($inventory, $transaction->storage_location_id);

        if ($properInventoryItem->quantity < $transaction->quantity) {
            throw new Exception("Insufficient quantity. Product: " . $inventory->product->name . ". Need quantity: " . $transaction->quantity);
        }

        $properInventoryItem->quantity -= $transaction->quantity;
        $properInventoryItem->save();
    }

    /**
     * @throws Exception
     */
    public function removeStock(
        $productId,
        $quantity,
        $warehouseId = null,
        $workStationId = null,
        $storageLocationId = null
    ): void {
        InventoryTransaction::query()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'cost' => 0,
            'warehouse_id' => $warehouseId,
            'work_station_id' => $workStationId,
            'storage_location_id' => $storageLocationId,
            'type' => TransactionType::Out,
        ]);
    }

    /**
     * @throws Exception
     */
    public function removeMiniStock($productId, $quantity, $workStationId = null): void
    {
        $miniInventory = $this->inventoryService->getMiniInventory($productId, $workStationId);

        if ($miniInventory->quantity < $quantity) {
            throw new Exception("Insufficient quantity. Product: " . $miniInventory->product->name . ". Actual quantity: " . $miniInventory->quantity);
        }

        $miniInventory->quantity -= $quantity;
        $miniInventory->save();
    }
}
