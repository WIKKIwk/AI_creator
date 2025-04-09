<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderProductType;
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

    /**
     * @throws Exception
     */
    public function test_complete_work(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Work station is not assigned to any production order');

        /** @var ProdOrderStep $firstStep */
        $firstStep = $this->prodOrder->steps()->first();
        $this->workStationService->completeWork($firstStep);
    }
}
