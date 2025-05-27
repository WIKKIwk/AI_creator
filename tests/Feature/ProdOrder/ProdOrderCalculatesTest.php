<?php

namespace Tests\Feature\ProdOrder;

use App\Enums\DurationUnit;
use App\Models\Inventory;
use App\Models\PerformanceRate;
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
            'organization_id' => $this->organization->id,
        ]);

        /** @var ProdTemplateStep $firstStep */
        $firstStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
        $firstStep->materials()->create([
            'product_id' => $productOne->id,
            'required_quantity' => $qtyOne = 7,
        ]);

        /** @var ProdTemplateStep $secondStep */
        $secondStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationSecond->id
        ]);
        $secondStep->materials()->create([
            'product_id' => $productTwo->id,
            'required_quantity' => $qtyTwo = 3,
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
            'organization_id' => $this->organization->id,
        ]);

        /** @var ProdTemplateStep $firstStep */
        $firstStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $productOne->id,
            'expected_quantity' => $qtyOne = 120,
        ]);
        PerformanceRate::query()->create([
            'work_station_id' => $this->workStationFirst->id,
            'product_id' => $productOne->id,
            'quantity' => 300,
            'duration' => 30,
            'duration_unit' => DurationUnit::Day
        ]);

        $firstExpected = ceil($qtyOne / (300 / 30));

        /** @var ProdTemplateStep $secondStep */
        $secondStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $productTwo->id,
            'expected_quantity' => $qtyTwo = 100,
        ]);
        PerformanceRate::query()->create([
            'work_station_id' => $this->workStationSecond->id,
            'product_id' => $productTwo->id,
            'quantity' => 370,
            'duration' => 5,
            'duration_unit' => DurationUnit::Week
        ]);
        $secondExpected = ceil($qtyTwo / (370 / 5 / 7));

        $expectedDeadline = $firstExpected + $secondExpected;
        $result = $this->prodOrderService->calculateDeadline($readyProduct->id);

        $this->assertEquals($expectedDeadline, $result);
    }
}
