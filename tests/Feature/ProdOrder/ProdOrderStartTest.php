<?php

namespace Tests\Feature\ProdOrder;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepProductStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\SupplyOrderState;
use App\Models\ProdOrder;
use App\Models\ProductCategory;
use App\Services\ProdOrderService;
use App\Services\TransactionService;
use App\Services\WorkStationService;
use Exception;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\TestCase;

class ProdOrderStartTest extends TestCase
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

        $this->prodOrder = ProdOrder::factory()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,

            // Confirmed
            'confirmed_at' => now(),
            'confirmed_by' => $this->user->id,
        ]);

        $this->prodOrderService = app(ProdOrderService::class);
        $this->workStationService = app(WorkStationService::class);
        $this->transactionService = app(TransactionService::class);
    }

    public function test_number_generation(): void
    {
        $cat = ProductCategory::factory()->create([
            'name' => 'Test Category',
            'code' => 'TEST-CAT',
            'organization_id' => $this->organization->id,
        ]);
        $product = $this->createProduct([
            'name' => 'Red Widget',
            'code' => 'RED-123',
            'product_category_id' => $cat->id,
        ]);

        $prodOrder = ProdOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'offer_price' => 200,
        ]);

        $this->assertEquals('PO-ORGRED-123' . now()->format('dmy'), $prodOrder->number);
    }

    public function test_start_not_confirmed(): void
    {
        $this->prodOrder->update(['confirmed_at' => null]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ProdOrder is not confirmed yet');

        $this->prodOrderService->start($this->prodOrder);
    }

    public function test_start_basic(): void
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
            'sequence' => 1,
            'status' => ProdOrderStepStatus::InProgress,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 3,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $this->rawMaterial->id,
            'status' => ProdOrderStepProductStatus::InProgress,
            'required_quantity' => 3,
            'available_quantity' => 0,
        ]);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $secondStep->id,
            'sequence' => 2,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 3,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $secondStep->id,
            'product_id' => $this->semiFinishedMaterial->id,
            'status' => ProdOrderStepProductStatus::InProgress,
            'required_quantity' => 3,
            'available_quantity' => 0,
        ]);

        $this->assertDatabaseHas('supply_orders', [
            'warehouse_id' => $this->prodOrder->group->warehouse_id,
            'prod_order_id' => $this->prodOrder->id,
            'state' => SupplyOrderState::Created->value,
            'product_category_id' => $this->rawMaterial->product_category_id,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'product_id' => $this->rawMaterial->id,
            'expected_quantity' => 3,
            'actual_quantity' => 0,
        ]);
    }

    public function test_start_with_materials_in_stock(): void
    {
        $this->actingAs($this->user);

        $inventoryItem = $this->transactionService->addStock(
            $this->rawMaterial->id,
            5,
            $this->prodOrder->group->warehouse_id
        );

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
            'expected_quantity' => 3,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'prod_order_step_id' => $firstStep->id,
            'product_id' => $this->rawMaterial->id,
            'status' => ProdOrderStepProductStatus::InProgress,
            'required_quantity' => 3,
            'available_quantity' => 3,
        ]);

        $this->assertDatabaseHas('prod_order_steps', [
            'id' => $secondStep->id,
            'prod_order_id' => $this->prodOrder->id,
            'work_station_id' => $this->workStationSecond->id,
            'sequence' => 2,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 3,
        ]);
        $this->assertDatabaseMissing('prod_order_step_products', [
            'prod_order_step_id' => $secondStep->id,
            'product_id' => $this->semiFinishedMaterial->id,
            'status' => ProdOrderStepProductStatus::InProgress,
            'required_quantity' => 3,
            'available_quantity' => 3,
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
