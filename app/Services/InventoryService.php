<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\MiniInventory;
use Illuminate\Database\Eloquent\Collection;

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

    public function getInventoryItem(Inventory $inventory, $storageLocationId = null): InventoryItem
    {
        /** @var InventoryItem $inventoryItem */
        $inventoryItem = $inventory->items()
            ->where('quantity', '>', 0)
            ->where('storage_location_id', $storageLocationId)
            ->orWhereNot('storage_location_id', $storageLocationId)
            ->orderByRaw("CASE WHEN storage_location_id = ? THEN 0 WHEN storage_location_id IS NULL THEN 1 ELSE 2 END", [$storageLocationId])
            ->orderBy('created_at')
            ->first();

        return $inventoryItem;
    }

    public function getInventoryItems(Inventory $inventory, $storageLocationId = null): Collection
    {
        return $inventory->items()
            ->where(function($query) use ($storageLocationId) {
                $query->where('storage_location_id', $storageLocationId)
                    ->orWhereNot('storage_location_id', $storageLocationId);
            })
            ->orderByRaw("CASE WHEN storage_location_id = ? THEN 0 WHEN storage_location_id IS NULL THEN 1 ELSE 2 END", [$storageLocationId])
            ->orderBy('created_at')
            ->get();
    }
}
