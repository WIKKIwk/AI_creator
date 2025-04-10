<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderProductStatus;
use App\Enums\ProdOrderProductType;
use App\Enums\StepProductType;
use App\Models\MiniInventory;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\Product;
use App\Models\WorkStation;
use App\Services\WorkStationService;
use Exception;
use Tests\TestCase;

class CompleteWorkStationWorkTest extends TestCase
{
    protected ProdOrder $prodOrder;
    protected Product $rawMaterial;
    protected Product $semiFinishedMaterial;
    protected Product $readyProduct;
    protected WorkStation $workStationFirst;
    protected WorkStation $workStationSecond;
    protected WorkStationService $workStationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workStationFirst = WorkStation::factory()->create(['name' => 'First Work Station']);
        $this->workStationSecond = WorkStation::factory()->create(['name' => 'Second Work Station']);

        $this->rawMaterial = $this->createProduct(['name' => 'Raw Material']);
        $this->semiFinishedMaterial = $this->createProduct(['name' => 'Semi Finished Product']);
        $this->readyProduct = $this->createProduct(['name' => 'Ready Product']);

        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->create([
            'agent_id' => $this->agent->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,
            'status' => OrderStatus::Pending
        ]);
        $this->prodOrder = $prodOrder;

        /** @var ProdOrderStep $stepFirst */
        $stepFirst = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
        $stepFirst->requiredItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 1,
            'type' => ProdOrderProductType::Required
        ]);
        $stepFirst->expectedItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 1,
            'type' => ProdOrderProductType::Expected
        ]);

        /** @var ProdOrderStep $stepSecond */
        $stepSecond = $this->prodOrder->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id
        ]);
        $stepSecond->requiredItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 1,
            'type' => ProdOrderProductType::Required
        ]);
        $stepSecond->expectedItems()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => 1,
            'type' => ProdOrderProductType::Expected
        ]);

        $this->workStationService = app(WorkStationService::class);
    }

    public function test_complete_work_basic(): void
    {
        // Add 10 raw materials to mini inventory
        $miniInventory = MiniInventory::query()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 10,
            'unit_cost' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->first();

        $firstStep->actualItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 1,
            'type' => StepProductType::Actual
        ]);

        $this->workStationService->completeWork($firstStep);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'status' => ProdOrderProductStatus::Completed
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniInventory->id,
            'quantity' => 9,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
    }

    public function test_complete_work_insufficient(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient quantity. Product: Raw Material. Actual quantity: 0');

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->first();
        $firstStep->actualItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 1,
            'type' => StepProductType::Actual
        ]);

        $this->workStationService->completeWork($firstStep);
    }

    public function test_complete_work_no_actual_items(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No actual items found for this step.');

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->first();
        $this->workStationService->completeWork($firstStep);
    }
}
