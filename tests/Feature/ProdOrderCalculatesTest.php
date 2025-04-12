<?php

namespace Tests\Feature;

use App\Enums\DurationUnit;
use App\Enums\OrderStatus;
use App\Enums\ProdOrderProductStatus;
use App\Enums\StepProductType;
use App\Models\Inventory;
use App\Models\MiniInventory;
use App\Models\PerformanceRate;
use App\Models\ProdOrderStep;
use App\Models\ProdTemplate;
use App\Models\ProdTemplateStep;
use App\Models\WorkStation;
use App\Services\ProdOrderService;
use Tests\TestCase;

class ProdOrderCalculatesTest extends TestCase
{
    protected ProdOrderService $prodOrderService;
    protected WorkStation $workStationFirst;
    protected WorkStation $workStationSecond;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workStationFirst = WorkStation::factory()->create(['name' => 'First Work Station']);
        $this->workStationSecond = WorkStation::factory()->create(['name' => 'Second Work Station']);

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function test_calculate_expected_cost(): void
    {
        $this->actingAs($this->user);

        $productOne = $this->createProduct(['name' => 'Test Product 1']);
        $productTwo = $this->createProduct(['name' => 'Test Product 2']);
        $readyProduct = $this->createProduct(['name' => 'Ready Product']);

        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->create([
            'name' => 'Test Template',
            'product_id' => $readyProduct->id,
        ]);

        /** @var ProdTemplateStep $firstStep */
        $firstStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
        $firstStep->productItems()->create([
            'product_id' => $productOne->id,
            'quantity' => $qtyOne = 7,
            'type' => StepProductType::Required
        ]);

        /** @var ProdTemplateStep $secondStep */
        $secondStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationSecond->id
        ]);
        $secondStep->productItems()->create([
            'product_id' => $productTwo->id,
            'quantity' => $qtyTwo = 3,
            'type' => StepProductType::Required
        ]);

        Inventory::query()->create([
            'product_id' => $productOne->id,
            'unit_cost' => $costOne = 15,
            'quantity' => 0,
            'warehouse_id' => $this->warehouse->id
        ]);
        Inventory::query()->create([
            'product_id' => $productTwo->id,
            'unit_cost' => $costTwo = 12,
            'quantity' => 0,
            'warehouse_id' => $this->warehouse->id
        ]);

        $expectedCost = ($costOne * $qtyOne) + ($costTwo * $qtyTwo);
        $result = $this->prodOrderService->calculateTotalCost($readyProduct->id, $this->warehouse->id);

        $this->assertEquals($expectedCost, $result);
    }

    public function test_calculate_expected_deadline(): void
    {
        $this->actingAs($this->user);

        $productOne = $this->createProduct(['name' => 'Test Product 1']);
        $productTwo = $this->createProduct(['name' => 'Test Product 2']);
        $readyProduct = $this->createProduct(['name' => 'Ready Product']);

        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->create([
            'name' => 'Test Template',
            'product_id' => $readyProduct->id,
        ]);

        /** @var ProdTemplateStep $firstStep */
        $firstStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
        $firstStep->productItems()->create([
            'product_id' => $productOne->id,
            'quantity' => $qtyOne = 120,
            'type' => StepProductType::Expected
        ]);
        PerformanceRate::query()->create([
            'work_station_id' => $this->workStationFirst->id,
            'product_id' => $productOne->id,
            'quantity' => 300,
            'duration' => 30,
            'duration_unit' => DurationUnit::Day
        ]);

        /** @var ProdTemplateStep $secondStep */
        $secondStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationSecond->id
        ]);
        $secondStep->productItems()->create([
            'product_id' => $productTwo->id,
            'quantity' => $qtyTwo = 80,
            'type' => StepProductType::Expected
        ]);
        PerformanceRate::query()->create([
            'work_station_id' => $this->workStationSecond->id,
            'product_id' => $productTwo->id,
            'quantity' => 370,
            'duration' => 5,
            'duration_unit' => DurationUnit::Week
        ]);

        $expectedDeadline = 12 + 8;
        $result = $this->prodOrderService->calculateDeadline($readyProduct->id);

        $this->assertEquals($expectedDeadline, $result);
    }
}
