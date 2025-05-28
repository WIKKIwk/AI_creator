<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Inventory\Inventory;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\TransactionService;
use Tests\TestCase;

class InventoryOutTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected TransactionService $transactionService;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);

        $this->inventoryService = app(InventoryService::class);
        $this->transactionService = app(TransactionService::class);
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
        ]);
    }

    public function test_inventory_out_basic(): void
    {
        /** @var Inventory $inventory */
        $inventory = Inventory::query()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
            'unit_cost' => 5.00,
        ]);

        $location_1 = $this->warehouse->locations()->create(['name' => 'Location 1']);
        $inventoryItem_1 = $inventory->items()->create([
            'quantity' => 5,
            'storage_location_id' => $location_1->id,
        ]);

        $location_2 = $this->warehouse->locations()->create(['name' => 'Location 2']);
        $inventoryItem_2 = $inventory->items()->create([
            'quantity' => 5,
            'storage_location_id' => $location_2->id,
        ]);

        // Test remove stock method
        $this->transactionService->removeStock(
            $this->product->id,
            6,
            $this->warehouse->id,
            storageLocationId: $location_2->id,
        );

        // Check if the inventory item quantities are updated correctly
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem_1->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem_2->id,
            'quantity' => 0,
        ]);

        // Creates two transaction from two separate storages
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'type' => TransactionType::Out,
            'storage_location_id' => $location_2->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'type' => TransactionType::Out,
            'storage_location_id' => $location_1->id,
        ]);
    }
}
