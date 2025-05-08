<?php

namespace Tests\Feature;

use App\Enums\StepProductType;
use App\Models\ProdOrder;
use App\Models\ProdTemplate;
use App\Models\ProdTemplateStep;
use App\Models\WorkStation;
use App\Services\ProdOrderService;
use App\Services\WorkStationService;
use Tests\TestCase;

class ProdOrderCheckStartTest extends TestCase
{
    use HasProdTemplate;

    protected ProdTemplate $prodTemplate;
    protected ProdOrder $prodOrder;
    protected ProdOrderService $prodOrderService;
    protected WorkStationService $workStationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workStationFirst = WorkStation::factory()->create(['name' => 'First Work Station']);
        $this->workStationSecond = WorkStation::factory()->create(['name' => 'Second Work Station']);

        $this->rawMaterial = $this->createProduct(['name' => 'Raw Material']);
        $this->rawMaterial2 = $this->createProduct(['name' => 'Raw Material 2']);
        $this->semiFinishedMaterial = $this->createProduct(['name' => 'Semi Finished Product']);
        $this->readyProduct = $this->createProduct(['name' => 'Ready Product']);

        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->create([
            'name' => 'Test Template',
            'product_id' => $this->readyProduct->id,
        ]);
        $this->prodTemplate = $prodTemplate;

        /** @var ProdTemplateStep $stepFirstTemplate */
        $stepFirstTemplate = $this->prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $stepFirstTemplate->productItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 4,
            'type' => StepProductType::Required
        ]);
        $stepFirstTemplate->productItems()->create([
            'product_id' => $this->rawMaterial2->id,
            'quantity' => 3,
            'type' => StepProductType::Required
        ]);

        /** @var ProdTemplateStep $stepSecondTemplate */
        $stepSecondTemplate = $this->prodTemplate->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 1,
        ]);
        $stepSecondTemplate->productItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 1,
            'type' => StepProductType::Required
        ]);

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

    public function test_check_start_prod_order(): void
    {
        $this->actingAs($this->user);
        $result = $this->prodOrderService->checkStart($this->prodOrder);

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result);

        $firstStep = $this->prodTemplate->firstStep;

        $this->assertCount(2, $result);

        foreach ($result as $productId => $item) {
            $actualStepProduct = $firstStep->requiredItems()->where('product_id', $productId)->first();
            $this->assertEquals($actualStepProduct->quantity * $this->prodOrder->quantity, $item['quantity']);
        }
    }
}
