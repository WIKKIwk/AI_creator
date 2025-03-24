<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\MiniInventory;

class InventoryService
{
    public function getInventory($productId, $warehouseId): Inventory
    {
        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$inventory) {
            $inventory = Inventory::query()->create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'unit_cost' => 0,
            ]);
        }

        return $inventory;
    }

    public function getMiniInventory($productId, $workStationId): MiniInventory
    {
        $inventory = MiniInventory::query()
            ->where('product_id', $productId)
            ->where('work_station_id', $workStationId)
            ->first();

        if (!$inventory) {
            $inventory = MiniInventory::query()->create([
                'product_id' => $productId,
                'work_station_id' => $workStationId,
                'quantity' => 0,
                'unit_cost' => 0,
            ]);
        }

        return $inventory;
    }

    public function getInventoryItem(Inventory $inventory, $storageLocationId): InventoryItem
    {
        $properInventoryItem = $inventory->items->first(function ($item) use ($storageLocationId) {
            $hasQuantity = $item->quantity > 0;
            if ($storageLocationId) {
                return $item->storage_location_id === $storageLocationId && $hasQuantity;
            }
            return $hasQuantity;
        });

        if (!$properInventoryItem) {
            $properInventoryItem = new InventoryItem([
                'inventory_id' => $inventory->id,
                'quantity' => 0,
                'storage_location_id' => $storageLocationId,
            ]);
        }

        return $properInventoryItem;
    }
}
