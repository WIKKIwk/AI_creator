<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderProductStatus;
use App\Enums\StepProductType;
use App\Enums\TransactionType;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\MiniInventory;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Services\ProdOrderService;
use App\Services\WorkStationService;
use Exception;
use Tests\TestCase;

class ProdOrderApproveTest extends TestCase
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

    public function test_next_step_failed(): void
    {
        $this->actingAs($this->user);

        /** @var ProdOrderStep $step */
        $step = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => ProdOrderProductStatus::InProgress
        ]);
        $this->prodOrder->update(['current_step_id' => $step->id]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Current step is not completed');
        $this->prodOrderService->next($this->prodOrder);
    }

    public function test_approve_not_completed(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ProdOrder is not completed yet');
        $this->prodOrderService->approve($this->prodOrder);
    }

    public function test_approve_success(): void
    {
        $this->actingAs($this->user);
        $this->prodOrder->update(['status' => OrderStatus::Completed]);

        $inventory = Inventory::query()->create([
            'product_id' => $this->readyProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
            'unit_cost' => 0,
        ]);

        /** @var ProdOrderStep $currentStep */
        $currentStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => ProdOrderProductStatus::Completed
        ]);
        $this->prodOrder->update(['current_step_id' => $currentStep->id]);

        // Add required quantities to MiniInventory
        $miniInventoryReady = MiniInventory::query()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => $stockQty = 7,
            'work_station_id' => $currentStep->work_station_id,
            'unit_cost' => 0
        ]);

        $currentStep->productItems()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => $expectedQty = 5,
            'type' => StepProductType::Expected
        ]);

        $this->prodOrderService->approve($this->prodOrder);

        // Check mini inventory for the current step
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniInventoryReady->id,
            'product_id' => $this->readyProduct->id,
            'quantity' => $stockQty - $expectedQty,
            'work_station_id' => $currentStep->work_station_id
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory->id,
            'quantity' => $expectedQty,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'work_station_id' => $currentStep->work_station_id,
            'product_id' => $this->readyProduct->id,
            'quantity' => $expectedQty,
            'type' => TransactionType::In
        ]);

        $this->assertDatabaseHas('prod_orders', [
            'id' => $this->prodOrder->id,
            'current_step_id' => $currentStep->id,
            'status' => OrderStatus::Approved
        ]);
    }

    public function test_next_step_last(): void
    {
        $this->actingAs($this->user);

        /** @var ProdOrderStep $currentStep */
        $currentStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => ProdOrderProductStatus::Completed
        ]);
        $this->prodOrder->update(['current_step_id' => $currentStep->id]);

        // Add required quantities to MiniInventory
        $miniInventoryReady = MiniInventory::query()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => $stockQty = 7,
            'work_station_id' => $currentStep->work_station_id,
            'unit_cost' => 0
        ]);

        // Create product items for the step
        $currentStep->productItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 10,
            'type' => StepProductType::Actual
        ]);
        $currentStep->productItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 8,
            'type' => StepProductType::Actual
        ]);
        $currentStep->productItems()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => $expectedQty = 5,
            'type' => StepProductType::Expected
        ]);

        $this->prodOrderService->next($this->prodOrder);

        // Check mini inventory for the current step
        $this->assertDatabaseHas('mini_inventories', [
            'id' => $miniInventoryReady->id,
            'product_id' => $this->readyProduct->id,
            'quantity' => $stockQty,
            'work_station_id' => $currentStep->work_station_id
        ]);

        $this->assertDatabaseHas('prod_orders', [
            'id' => $this->prodOrder->id,
            'current_step_id' => $currentStep->id,
            'status' => OrderStatus::Completed
        ]);
    }
}
