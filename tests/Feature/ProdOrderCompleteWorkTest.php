<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\StepProductType;
use App\Models\MiniInventory;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Services\ProdOrderService;
use App\Services\WorkStationService;
use Exception;
use Tests\TestCase;

class ProdOrderCompleteWorkTest extends TestCase
{
    use HasProdTemplate;

    protected ProdOrder $prodOrder;
    protected ProdOrderService $prodOrderService;
    protected WorkStationService $workStationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProdTemplate();

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

        $this->prodOrderService = app(ProdOrderService::class);
        $this->workStationService = app(WorkStationService::class);
    }

    public function test_complete_work(): void
    {
        $this->actingAs($this->user);

        /** @var ProdOrderStep $step */
        $step = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => ProdOrderStepStatus::InProgress,
            'output_product_id' => $this->readyProduct->id,
        ]);

        // Add required quantities to MiniInventory
        $miniInventoryRaw = MiniInventory::query()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 12,
            'work_station_id' => $step->work_station_id,
            'unit_cost' => 0
        ]);
        $miniInventorySfi = MiniInventory::query()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 12,
            'work_station_id' => $step->work_station_id,
            'unit_cost' => 0
        ]);

        // Create product items for the step
        $step->productItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 10,
            'type' => StepProductType::Actual
        ]);
        $step->productItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 8,
            'type' => StepProductType::Actual
        ]);

        $this->prodOrderService->completeWork($step, 5);

        // Check MiniInventories for proper quantities
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniInventoryRaw->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 2,
            'work_station_id' => $step->work_station_id,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniInventorySfi->id,
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 4,
            'work_station_id' => $step->work_station_id,
        ]);
        $this->assertDatabaseHas('mini_inventories', [
            'product_id' => $this->readyProduct->id,
            'quantity' => 5,
            'work_station_id' => $step->work_station_id,
        ]);

        // Check Step status Completed
        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $step->id,
            'status' => ProdOrderStepStatus::Completed
        ]);
    }

    public function test_complete_work_insufficient(): void
    {
        $this->actingAs($this->user);

        /** @var ProdOrderStep $step */
        $step = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => ProdOrderStepStatus::InProgress,
            'output_product_id' => $this->readyProduct->id,
        ]);

        // Add required quantities to MiniInventory
        MiniInventory::query()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => $stockQty = 9,
            'work_station_id' => $step->work_station_id,
            'unit_cost' => 0
        ]);

        // Create product items for the step
        $step->productItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 10,
            'type' => StepProductType::Actual
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Insufficient quantity. Product: {$this->rawMaterial->name}. Actual quantity: $stockQty"
        );

        $this->prodOrderService->completeWork($step, 5);
    }
}
