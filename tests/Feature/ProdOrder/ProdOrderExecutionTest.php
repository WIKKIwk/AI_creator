<?php

namespace ProdOrder;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
use App\Models\Inventory\Inventory;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepExecution;
use Exception;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\TestCase;

class ProdOrderExecutionTest extends TestCase
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

    public function test_create_execution_by_form(): void
    {
        /** @var ProdOrderStep $firstStep */
        /** @var ProdOrderStep $secondStep */

        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'output_quantity' => 0,
            'expected_quantity' => 100,
        ]);
        $firstStepMaterial = $firstStep->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => 10,
            'available_quantity' => 100,
        ]);

        $secondStep = $this->prodOrder->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 100,
        ]);

        $firstMiniStock = $this->transactionService->addMiniStock(
            $this->rawMaterial->id,
            100,
            $this->workStationFirst->id
        );
        $secondMiniStock = $this->inventoryService->getMiniInventory(
            $this->semiFinishedMaterial->id,
            $this->workStationSecond->id
        );

        $this->assertEquals(100, $firstMiniStock->quantity);
        $this->assertEquals(0, $secondMiniStock->quantity);

        $this->prodOrderService->createExecutionByForm($firstStep, [
            'output_quantity' => 30,
            'materials' => [
                ['product_id' => $this->rawMaterial->id, 'used_quantity' => 10]
            ]
        ]);

        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => 90,
            'used_quantity' => 10,
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'output_quantity' => 0,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $firstStep->work_station_id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 90,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $firstStep->work_station_id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 30,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $secondMiniStock->id,
            'quantity' => 0,
        ]);
    }

    public function test_approve_execution_success(): void
    {
        /** @var ProdOrderStep $firstStep */
        /** @var ProdOrderStep $secondStep */

        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'output_quantity' => 0,
            'expected_quantity' => 100,
        ]);
        $firstStepMaterial = $firstStep->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => 10,
            'available_quantity' => 100,
            'used_quantity' => 0,
        ]);

        $secondStep = $this->prodOrder->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 100,
        ]);
        $secondStepMaterial = $secondStep->materials()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'required_quantity' => 10,
            'available_quantity' => 0,
        ]);

        $firstMiniStock = $this->transactionService->addMiniStock(
            $this->rawMaterial->id,
            100,
            $this->workStationFirst->id
        );

        $firstExecution = $this->prodOrderService->createExecutionByForm($firstStep, [
            'output_quantity' => 30,
            'materials' => [['product_id' => $this->rawMaterial->id, 'used_quantity' => 10]]
        ]);

        $this->prodOrderService->approveExecution($firstExecution);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $firstExecution->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'output_quantity' => 30,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => 90,
            'used_quantity' => 10,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $firstStep->work_station_id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 90,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $firstStep->work_station_id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 0,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $secondStep->work_station_id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 30,
        ]);

        $secondStepMaterial->update(['available_quantity' => 30]);

        $secondExecution = $this->prodOrderService->createExecutionByForm($secondStep, [
            'output_quantity' => 30,
            'materials' => [
                ['product_id' => $this->semiFinishedMaterial->id, 'used_quantity' => 20]
            ]
        ]);

        $this->prodOrderService->approveExecution($secondExecution);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $secondExecution->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $secondStep->id,
            'output_quantity' => 30,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $secondStepMaterial->id,
            'available_quantity' => 10,
            'used_quantity' => 20,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $secondStep->work_station_id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $secondStep->work_station_id,
            'product_id' => $this->readyProduct->id,
            'quantity' => 0,
        ]);

        /** @var Inventory $inventory */
        $inventory = Inventory::query()
            ->where('product_id', $this->readyProduct->id)
            ->where('warehouse_id', $this->prodOrder->group->warehouse_id)
            ->first();
        $this->assertEquals(30, $inventory->quantity);

        $firstExecution2 = $this->prodOrderService->createExecutionByForm($firstStep, [
            'output_quantity' => 70,
            'materials' => [['product_id' => $this->rawMaterial->id, 'used_quantity' => 20]]
        ]);

        $this->prodOrderService->approveExecution($firstExecution2);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $firstExecution2->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'output_quantity' => 100,
            'status' => ProdOrderStepStatus::Completed,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => 70,
            'used_quantity' => 30,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $firstStep->work_station_id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 70,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $firstStep->work_station_id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 0,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'work_station_id' => $secondStep->work_station_id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 80,
        ]);
    }

    public function test_approve_execution_approved(): void
    {
        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 100,
        ]);

        /** @var ProdOrderStepExecution $firstExecution */
        $firstExecution = $firstStep->executions()->create([
            'output_quantity' => 30,
            'approved_at' => now(),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Execution is already approved');

        $this->prodOrderService->approveExecution($firstExecution);
    }

    public function test_approve_execution_not_enough(): void
    {
        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 100,
        ]);
        $firstStep->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => 10,
            'available_quantity' => 10,
            'used_quantity' => 0,
        ]);

        /** @var ProdOrderStepExecution $firstExecution */
        $firstExecution = $firstStep->executions()->create(['output_quantity' => 30]);
        $firstExecution->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'used_quantity' => 11,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient quantity');

        $this->prodOrderService->approveExecution($firstExecution);
    }
}
