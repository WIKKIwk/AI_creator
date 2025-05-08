<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StepProductType;
use App\Models\Inventory;
use App\Models\ProdOrder;
use App\Models\ProdTemplate;
use App\Models\ProdTemplateStep;
use App\Models\Product;
use App\Models\WorkStation;
use App\Services\ProdOrderService;
use App\Services\WorkStationService;
use Exception;
use Tests\TestCase;

class ProdOrderStartTest extends TestCase
{
    use HasProdTemplate;

    protected ProdOrder $prodOrder;
    protected ProdOrderService $prodOrderService;
    protected WorkStationService $workStationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProdTemplate();

        $this->prodOrder = ProdOrder::factory()->create([
            'agent_id' => $this->agent->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,

            // Confirmed
            'confirmed_at' => now(),
            'confirmed_by' => $this->user->id,
        ]);

        $this->prodOrderService = app(ProdOrderService::class);
        $this->workStationService = app(WorkStationService::class);
    }

    public function test_start_prod_order_not_confirmed(): void
    {
        $this->actingAs($this->user);
        $this->prodOrder->update(['confirmed_at' => null]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ProdOrder is not confirmed yet');

        $this->prodOrderService->start($this->prodOrder);
    }

    public function test_start_prod_order_basic(): void
    {
        $this->actingAs($this->user);
        $this->prodOrderService->start($this->prodOrder);

        $firstStep = $this->prodOrder->firstStep;
        $secondStep = $this->prodOrder->steps()->where('sequence', 2)->first();

        $this->assertDatabaseHas('prod_orders', [
            'id' => $this->prodOrder->id,
            'status' => OrderStatus::Blocked,
        ]);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationFirst->id,
            'sequence' => 1,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $this->assertDatabaseMissing('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'type' => StepProductType::Actual
        ]);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $secondStep->id,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationSecond->id,
            'sequence' => 2,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 1,
        ]);
        $this->assertDatabaseMissing('prod_order_step_products', [
            'prod_order_step_id' => $secondStep->id,
            'type' => StepProductType::Actual
        ]);

        $this->assertDatabaseHas('supply_orders', [
            'warehouse_id' => $this->prodOrder->warehouse_id,
            'prod_order_id' => $this->prodOrder->id,
            'status' => OrderStatus::Pending,
            'product_id' => $this->rawMaterial->id,
            'quantity' => $this->prodOrder->quantity,
        ]);
    }

    public function test_start_prod_order_with_materials_in_stock(): void
    {
        $this->actingAs($this->user);

        /** @var Inventory $inventory */
        $inventory = Inventory::query()->create([
            'warehouse_id' => $this->prodOrder->warehouse_id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 5,
            'unit_cost' => 100,
        ]);
        $inventoryItem = $inventory->items()->create(['quantity' => 5]);

        $this->prodOrderService->start($this->prodOrder);

        $firstStep = $this->prodOrder->firstStep;
        $secondStep = $this->prodOrder->steps()->where('sequence', 2)->first();

        // Check ProdOrder and its Steps created correctly
        $this->assertDatabaseHas('prod_orders', [
            'id' => $this->prodOrder->id,
            'status' => OrderStatus::Processing,
        ]);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $firstStep->id,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationFirst->id,
            'sequence' => 1,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 3,
            'type' => StepProductType::Actual
        ]);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $secondStep->id,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationSecond->id,
            'sequence' => 2,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 1,
        ]);
        $this->assertDatabaseMissing('prod_order_step_products', [
            'prod_order_step_id' => $secondStep->id,
            'type' => StepProductType::Actual
        ]);

        // Check Supply Orders created correctly
        $this->assertDatabaseMissing('supply_orders', [
            'prod_order_id' => $this->prodOrder->id
        ]);

        // Check Inventory Items reduced correctly
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'quantity' => 2,
        ]);
    }
}
