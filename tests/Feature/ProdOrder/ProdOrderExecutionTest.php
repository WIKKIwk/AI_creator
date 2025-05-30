<?php

namespace ProdOrder;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
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

    public function test_create_execution(): void
    {
        $this->markTestSkipped('This test is not implemented yet.');
    }

    public function test_approve_execution_success(): void
    {
        /** @var ProdOrderStep $firstStep */
        /** @var ProdOrderStep $secondStep */

        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
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
        $secondMiniStock = $this->inventoryService->getMiniInventory(
            $this->semiFinishedMaterial->id,
            $this->workStationSecond->id
        );
        $mainStock = $this->transactionService->addStock(
            $this->readyProduct->id,
            1,
            $this->prodOrder->group->warehouse_id
        );

        $this->assertEquals(100, $firstMiniStock->quantity);
        $this->assertEquals(0, $secondMiniStock->quantity);
        $this->assertEquals(1, $mainStock->quantity);

        /** @var ProdOrderStepExecution $firstExecution */
        $firstExecution = $firstStep->executions()->create(['output_quantity' => 30]);
        $firstExecution->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'used_quantity' => 10,
        ]);

        $this->prodOrderService->approveExecution($firstExecution);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $firstExecution->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => 90,
            'used_quantity' => 10,
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'output_quantity' => 30,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $firstMiniStock->id,
            'quantity' => 90,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $secondMiniStock->id,
            'quantity' => 30,
        ]);

        $secondStepMaterial->update(['available_quantity' => 30]);

        /** @var ProdOrderStepExecution $secondExecution */
        $secondExecution = $secondStep->executions()->create(['output_quantity' => 30]);
        $secondExecution->materials()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'used_quantity' => 20,
        ]);

        $this->prodOrderService->approveExecution($secondExecution);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $secondExecution->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $secondStepMaterial->id,
            'available_quantity' => 10,
            'used_quantity' => 20,
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $secondStepMaterial->id,
            'output_quantity' => 30,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $secondMiniStock->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $mainStock->id,
            'quantity' => 31,
        ]);

        $firstStepMaterial->update(['available_quantity' => 22]);

        /** @var ProdOrderStepExecution $firstExecution */
        $firstExecution2 = $firstStep->executions()->create(['output_quantity' => 70]);
        $firstExecution2->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'used_quantity' => 20,
        ]);

        $this->prodOrderService->approveExecution($firstExecution2);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $firstExecution2->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => 2,
            'used_quantity' => 30,
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'output_quantity' => 100,
            'status' => ProdOrderStepStatus::Completed,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $firstMiniStock->id,
            'quantity' => 70,
        ]);
    }

    public function test_approve_execution_last_step(): void
    {
        /** @var ProdOrderStep $firstStep */
        /** @var ProdOrderStep $secondStep */

        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
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
        $secondMiniStock = $this->inventoryService->getMiniInventory(
            $this->readyProduct->id,
            $this->workStationSecond->id
        );

        $mainStock = $this->transactionService->addStock(
            $this->readyProduct->id,
            1,
            $this->prodOrder->group->warehouse_id
        );

        $this->assertEquals(100, $firstMiniStock->quantity);
        $this->assertEquals(0, $secondMiniStock->quantity);
        $this->assertEquals(1, $mainStock->quantity);

        /** @var ProdOrderStepExecution $firstExecution */
        $firstExecution = $firstStep->executions()->create(['output_quantity' => 30]);
        $firstExecution->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'used_quantity' => 10,
        ]);

        $this->prodOrderService->approveExecution($firstExecution);

        $this->assertDatabaseHas('prod_order_step_executions', [
            'id' => $firstExecution->id,
            'approved_by' => $this->user->id
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => 90,
            'used_quantity' => 10,
        ]);
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStepMaterial->id,
            'output_quantity' => 30,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $firstMiniStock->id,
            'quantity' => 90,
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
        $this->expectExceptionMessage('Not enough available quantity');

        $this->prodOrderService->approveExecution($firstExecution);
    }
}
