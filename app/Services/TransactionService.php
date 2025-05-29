<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryTransaction;
use App\Models\Inventory\MiniInventory;
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
        $warehouseId,
        $cost = null,
        $storageLocationId = null,
        $workStationId = null,
        $withTransaction = true
    ): InventoryItem {
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero.');
        }

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
            $newInventoryItem = InventoryItem::query()->create([
                'inventory_id' => $inventory->id,
                'storage_location_id' => $storageLocationId,
                'quantity' => 0,
            ]);
            $inventoryItems->push($newInventoryItem);
        }

        /** @var InventoryItem $inventoryItem */
        $inventoryItem = $inventoryItems->first();
        $inventoryItem->quantity = $inventoryItem->quantity + $quantity;
        $inventoryItem->save();

        if ($withTransaction) {
            InventoryTransaction::query()->create([
                'product_id' => $inventory->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'storage_location_id' => $inventoryItem->storage_location_id,
                'work_station_id' => $workStationId,
                'quantity' => $quantity,
                'type' => TransactionType::In,
                'cost' => $cost ?? 0,
            ]);
        }

        return $inventoryItem;
    }

    public function getStockLackQty(
        $productId,
        $quantity,
        $warehouseId,
        $storageLocationId = null
    ): ?float {
        /** @var Collection<InventoryItem> $inventoryItems */
        $inventory = $this->inventoryService->getInventory($productId, $warehouseId);
        $inventoryItems = $this->inventoryService->getInventoryItems($inventory, $storageLocationId);

        // Check items in Stock
        $lackQuantity = $quantity;
        foreach ($inventoryItems as $inventoryItem) {
            if ($inventoryItem->quantity <= 0) {
                continue;
            }
            if ($lackQuantity <= 0) {
                break;
            }
            if ($inventoryItem->quantity >= $lackQuantity) {
                return 0;
            }

            $lackQuantity -= $inventoryItem->quantity;
        }

        return $lackQuantity;
    }

    public function getMiniStockLackQty($productId, $quantity, $workStationId): ?float
    {
        $miniInventory = $this->inventoryService->getMiniInventory($productId, $workStationId);

        return max(0, $quantity - $miniInventory->quantity);
    }

    public function removeStock(
        $productId,
        $quantity,
        $warehouseId,
        $workStationId = null,
        $storageLocationId = null
    ): ?float {
        /** @var Collection<InventoryItem> $inventoryItems */
        $inventory = $this->inventoryService->getInventory($productId, $warehouseId);
        $inventoryItems = $this->inventoryService->getInventoryItems($inventory, $storageLocationId);

        // Remove items from Stock
        $lackQuantity = $quantity;
        foreach ($inventoryItems as $inventoryItem) {
            if ($inventoryItem->quantity <= 0) {
                continue;
            }
            if ($lackQuantity <= 0) {
                break;
            }

            $quantityOut = min($inventoryItem->quantity, $lackQuantity);
            $inventoryItem->quantity -= $quantityOut;
            $lackQuantity -= $quantityOut;
            $inventoryItem->save();

            InventoryTransaction::query()->create([
                'product_id' => $inventory->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'storage_location_id' => $inventoryItem->storage_location_id,
                'work_station_id' => $workStationId,
                'quantity' => $quantityOut,
                'type' => TransactionType::Out,
                'cost' => $inventory->unit_cost,
            ]);
        }

        return $lackQuantity;
    }

    /**
     * @throws Exception
     */
    public function addMiniStock($productId, $quantity, $workStationId, $cost = null): MiniInventory
    {
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero.');
        }

        $miniInventory = $this->inventoryService->getMiniInventory($productId, $workStationId);

        if ($cost) {
            $totalCost = $miniInventory->quantity * $miniInventory->unit_cost + $cost;
            $totalQuantity = $miniInventory->quantity + $quantity;
            $averageCost = $totalCost / $totalQuantity;
            $miniInventory->unit_cost = round($averageCost, 2);
        }

        $miniInventory->quantity += $quantity;
        $miniInventory->save();

        return $miniInventory;
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

    public function removeMiniStockForce($productId, $quantity, $workStationId): ?float
    {
        $miniInventory = $this->inventoryService->getMiniInventory($productId, $workStationId);
        $prevQty = $miniInventory->quantity;
        $miniInventory->quantity -= min($prevQty, $quantity);
        $miniInventory->save();

        return max(0, $quantity - $prevQty);
    }
}
