<?php

namespace Tests\Feature\ProdOrder;

use App\Enums\OrderStatus;
use App\Enums\SupplyOrderState;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\MiniInventory;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\ProdOrderService;
use App\Services\TransactionService;
use App\Services\WorkStationService;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\TestCase;

class ProdOrderAddActualTest extends TestCase
{
    use HasProdTemplate;

    protected ProdOrder $prodOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);

        $this->createProdTemplate();

        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::factory()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,
            'status' => OrderStatus::Pending
        ]);
        $this->prodOrder = $prodOrder;
    }

    /**
     * @dataProvider lessMoreQtyProvider
     */
    public function test_add_actual_materials($quantity): void
    {
        $anotherProduct = $this->createProduct(['name' => 'Another Product']);

        $inventoryItem = $this->transactionService->addStock(
            $anotherProduct->id,
            $stockQty = 5,
            $this->prodOrder->getWarehouseId()
        );

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $firstStep->materials()->create([
            'product_id' => $anotherProduct->id,
            'required_quantity' => $requiredQty = 1,
            'available_quantity' => 0,
        ]);

        $insufficientQty = $quantity > $stockQty;

        $lackQuantity = $this->prodOrderService->updateMaterial($firstStep, $anotherProduct->id, $quantity);
        $this->assertEquals($insufficientQty ? ($quantity - $stockQty) : 0, $lackQuantity);

        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $anotherProduct->id,
            'required_quantity' => $requiredQty,
            'available_quantity' => min($quantity, $stockQty)
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => max($stockQty - $quantity, 0)
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $this->workStationFirst->id,
            'product_id' => $anotherProduct->id,
            'quantity' => min($quantity, $stockQty)
        ]);
    }

    public function test_check_materials_custom(): void
    {
        $anotherProduct = $this->createProduct(['name' => 'Another Product']);

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $firstStep->materials()->create([
            'product_id' => $anotherProduct->id,
            'required_quantity' => 1,
            'available_quantity' => 100,
        ]);

        $this->transactionService->addMiniStock($anotherProduct->id, 100, $firstStep->work_station_id);

        $insufficientAssetsByCat = $this->prodOrderService->checkMaterialsExact(
            $firstStep,
            $anotherProduct->id,
            $quantity = 110
        );
        $this->assertCount(1, $insufficientAssetsByCat);

        $data = $insufficientAssetsByCat[$anotherProduct->product_category_id][$anotherProduct->id];
        $this->assertEquals(10, $data['quantity']);
    }

    public function test_change_actual_custom1(): void
    {
        $anotherProduct = $this->createProduct(['name' => 'Another Product']);

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $firstStep->materials()->create([
            'product_id' => $anotherProduct->id,
            'required_quantity' => $requiredQty = 1,
            'available_quantity' => $availableQty = 100,
        ]);

        $inventory = Inventory::query()->create([
            'product_id' => $anotherProduct->id,
            'warehouse_id' => $this->prodOrder->getWarehouseId(),
            'unit_cost' => 0,
            'quantity' => 0,
        ]);
        $inventoryItem = InventoryItem::query()->create([
            'inventory_id' => $inventory->id,
            'quantity' => 0,
        ]);

        $miniInventory = $this->transactionService->addMiniStock(
            $anotherProduct->id,
            $miniStockQty = 100,
            $firstStep->work_station_id,
        );

        $lackQuantity = $this->prodOrderService->updateMaterialExact(
            $firstStep,
            $anotherProduct->id,
            $quantity = 110
        );
        $this->assertEquals(10, $lackQuantity);

        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $anotherProduct->id,
            'required_quantity' => $requiredQty,
            'available_quantity' => $availableQty
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => 0
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $this->workStationFirst->id,
            'product_id' => $anotherProduct->id,
            'quantity' => 100
        ]);

        if ($lackQuantity > 0) {
            /** @var SupplyOrder $supplyOrder */
            $supplyOrder = SupplyOrder::query()
                ->where('prod_order_id', $this->prodOrder->id)
                ->where('product_category_id', $anotherProduct->product_category_id)
                ->first();

            $this->assertDatabaseHas('supply_orders', [
                'id' => $supplyOrder->id,
                'state' => SupplyOrderState::Created,
                'status' => null,
            ]);
            $this->assertDatabaseHas('supply_order_products', [
                'supply_order_id' => $supplyOrder->id,
                'product_id' => $anotherProduct->id,
                'expected_quantity' => $lackQuantity,
                'actual_quantity' => 0,
            ]);
        }
    }

    public function test_change_actual_custom2(): void
    {
        $anotherProduct = $this->createProduct(['name' => 'Another Product']);

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $firstStep->materials()->create([
            'product_id' => $anotherProduct->id,
            'required_quantity' => 1,
            'available_quantity' => 0,
        ]);

        $inventoryItem = $this->transactionService->addStock(
            $anotherProduct->id,
            18,
            $this->prodOrder->getWarehouseId()
        );

        $miniInventory = $this->transactionService->addMiniStock(
            $anotherProduct->id,
            $miniStockQty = 2,
            $firstStep->work_station_id,
        );

        $lackQuantity = $this->prodOrderService->updateMaterialExact(
            $firstStep,
            $anotherProduct->id,
            $quantity = 18
        );
        $this->assertEquals(0, $lackQuantity);

        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $anotherProduct->id,
            'required_quantity' => 1,
            'available_quantity' => 18
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => 2
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $this->workStationFirst->id,
            'product_id' => $anotherProduct->id,
            'quantity' => 18
        ]);
    }

    /**
     * @dataProvider lessMoreQtyProvider
     */
    public function test_change_actual_materials($quantity): void
    {
        $inventoryItem = $this->transactionService->addStock(
            $this->rawMaterial->id,
            $stockQty = 5,
            $this->prodOrder->getWarehouseId()
        );

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $firstStepMaterial = $firstStep->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => $requiredQty = 6,
            'available_quantity' => $availableQty = 6,
        ]);

        /** @var MiniInventory $miniStock */
        $miniStock = $this->workStationFirst->miniInventories()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 6,
            'unit_cost' => 0
        ]);

        $lackQuantity = $this->prodOrderService->updateMaterial($firstStep, $this->rawMaterial->id, $quantity);
        $this->assertEquals(
            match ($quantity) {
                1 => 0,
                6 => 1,
                10 => 5,
                15 => 10,
            },
            $lackQuantity
        );

        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'required_quantity' => $requiredQty,
            'available_quantity' => match ($quantity) {
                1 => 7,
                6, 10, 15 => 11,
            }
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => max($stockQty - $quantity, 0)
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniStock->id,
            'quantity' => match ($quantity) {
                1 => 7,
                6, 10, 15 => 11,
            }
        ]);

        if ($lackQuantity > 0) {
            /** @var SupplyOrder $supplyOrder */
            $supplyOrder = SupplyOrder::query()
                ->where('prod_order_id', $this->prodOrder->id)
                ->where('product_category_id', $this->rawMaterial->product_category_id)
                ->first();

            $this->assertDatabaseHas('supply_orders', [
                'id' => $supplyOrder->id,
                'state' => SupplyOrderState::Created,
                'status' => null,
            ]);
            $this->assertDatabaseHas('supply_order_products', [
                'supply_order_id' => $supplyOrder->id,
                'product_id' => $this->rawMaterial->id,
                'expected_quantity' => match ($quantity) {
                    6 => 1,
                    10 => 5,
                    15 => 10,
                },
                'actual_quantity' => 0,
            ]);
        }
    }

    public static function lessMoreQtyProvider(): array
    {
        return [
            'less' => [1],
            'equal' => [6],
            'more' => [10],
            'more than 10' => [15],
        ];
    }
}
