<?php

namespace Tests\Feature;

use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\Product;
use App\Models\WorkStation;
use App\Services\WorkStationService;
use Tests\TestCase;

class CompleteWorkStationWorkTest extends TestCase
{
    protected ProdOrder $prodOrder;
    protected Product $rawMaterial;
    protected Product $semiFinishedMaterialFirst;
    protected Product $semiFinishedMaterialSecond;
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
        $this->semiFinishedMaterialFirst = $this->createProduct(['name' => 'Semi Finished Product']);
        $this->semiFinishedMaterialSecond = $this->createProduct(['name' => 'Semi Finished Product']);
        $this->readyProduct = $this->createProduct(['name' => 'Ready Product']);

        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->create([
            'agent_id' => $this->agent->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,
        ]);
        $this->prodOrder = $prodOrder;

        /** @var ProdOrderStep $stepFirst */
        $stepFirst = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
        $stepFirst->requiredItems()()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 1
        ]);

        /** @var ProdOrderStep $stepSecond */
        $stepSecond = $this->prodOrder->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id
        ]);

        $this->workStationService = app(WorkStationService::class);
    }

    public function test_complete_work(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
