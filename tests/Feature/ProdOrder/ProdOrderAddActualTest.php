<?php

namespace Tests\Feature\ProdOrder;

use App\Enums\OrderStatus;
use App\Enums\SupplyOrderState;
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
    protected ProdOrderService $prodOrderService;
    protected WorkStationService $workStationService;
    protected TransactionService $transactionService;

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

        $this->prodOrderService = app(ProdOrderService::class);
        $this->workStationService = app(WorkStationService::class);
        $this->transactionService = app(TransactionService::class);
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

        $lackQuantity = $this->prodOrderService->changeMaterialAvailable($firstStep, $anotherProduct->id, $quantity);
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

    public function test_change_actual_custom(): void
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

        $inventoryItem = $this->transactionService->addStock(
            $anotherProduct->id,
            $stockQty = 0,
            $this->prodOrder->getWarehouseId()
        );
        $miniInventory = $this->transactionService->addMiniStock(
            $anotherProduct->id,
            $miniStockQty = 100,
            $firstStep->work_station_id,
        );

        $insufficientAssetsByCat = $this->prodOrderService->checkMaterials($firstStep, $anotherProduct->id, 110, true);
        $this->assertEmpty($insufficientAssetsByCat);
    }

    /**
     * @dataProvider lessMoreQtyProvider
     */
    public function test_edit_actual_materials($quantity): void
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

        $lackQuantity = $this->prodOrderService->changeMaterialAvailable($firstStep, $this->rawMaterial->id, $quantity);
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
