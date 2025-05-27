<?php

namespace Tests\Feature\ProdOrder;

use App\Enums\OrderStatus;
use App\Models\MiniInventory;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
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
    public function test_add_actual_materials($qty): void
    {
        $anotherProduct = $this->createProduct(['name' => 'Another Product']);

        $inventoryItem = $this->transactionService->addStock(
            $anotherProduct->id,
            $stockQty = 5,
            $this->prodOrder->group->warehouse_id
        );

        $insufficientQty = $qty > $stockQty;

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

        $lackQuantity = $this->prodOrderService->addMaterialAvailable($firstStep, $anotherProduct->id, $qty);
        $this->assertEquals($insufficientQty ? ($qty - $stockQty) : 0, $lackQuantity);

        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $anotherProduct->id,
            'required_quantity' => $requiredQty,
            'available_quantity' => min($qty, $stockQty)
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => max($stockQty - $qty, 0)
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $this->workStationFirst->id,
            'product_id' => $anotherProduct->id,
            'quantity' => min($qty, $stockQty)
        ]);
    }

    /**
     * @dataProvider lessMoreQtyProvider
     */
    public function test_edit_actual_materials($qty): void
    {
        $inventoryItem = $this->transactionService->addStock(
            $this->rawMaterial->id,
            $stockQty = 5,
            $this->prodOrder->group->warehouse_id
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
            'available_quantity' => $requiredQty,
        ]);

        /** @var MiniInventory $miniStock */
        $miniStock = $this->workStationFirst->miniInventories()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => $requiredQty,
            'unit_cost' => 0
        ]);

        $insufficientQty = $qty > $stockQty;

        $lackQuantity = $this->prodOrderService->addMaterialAvailable($firstStep, $this->rawMaterial->id, $qty);
        $this->assertEquals($insufficientQty ? ($qty - $stockQty) : 0, $lackQuantity);

        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'required_quantity' => $requiredQty,
            'available_quantity' => $requiredQty + min($qty, $stockQty),
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => max($stockQty - $qty, 0),
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniStock->id,
            'quantity' => $requiredQty + min($qty, $stockQty),
        ]);
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
