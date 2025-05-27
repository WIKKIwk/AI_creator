<?php

namespace Tests\Feature\ProdOrder;

use App\Models\ProdOrder;
use App\Models\ProdOrderStepProduct;
use App\Models\ProdTemplate;
use App\Models\ProdTemplateStep;
use App\Models\WorkStation;
use App\Services\ProdOrderService;
use App\Services\TransactionService;
use App\Services\WorkStationService;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\TestCase;

class ProdOrderCheckStartTest extends TestCase
{
    use HasProdTemplate;

    protected ProdTemplate $prodTemplate;
    protected ProdOrder $prodOrder;
    protected ProdOrderService $prodOrderService;
    protected TransactionService $transactionService;
    protected WorkStationService $workStationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);

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
            'organization_id' => $this->organization->id,
        ]);
        $this->prodTemplate = $prodTemplate;

        /** @var ProdTemplateStep $stepFirstTemplate */
        $stepFirstTemplate = $this->prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $stepFirstTemplate->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => 4
        ]);
        $stepFirstTemplate->materials()->create([
            'product_id' => $this->rawMaterial2->id,
            'required_quantity' => 3,
        ]);

        /** @var ProdTemplateStep $stepSecondTemplate */
        $stepSecondTemplate = $this->prodTemplate->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 1,
        ]);
        $stepSecondTemplate->materials()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'required_quantity' => 1,
        ]);

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

    public function test_check_start_basic(): void
    {
        $insufficientAssetsByCat = $this->prodOrderService->checkStart($this->prodOrder);
        $insufficientAssets = $insufficientAssetsByCat[$this->productCategory->id];

        $this->assertNotEmpty($insufficientAssets);
        $this->assertCount(2, $insufficientAssets);

        $firstStep = $this->prodTemplate->firstStep;
        foreach ($insufficientAssets as $productId => $item) {
            /** @var ProdOrderStepProduct $stepMaterial */
            $stepMaterial = $firstStep->materials()->where('product_id', $productId)->first();
            $this->assertEquals($stepMaterial->required_quantity * $this->prodOrder->quantity, $item['quantity']);
        }
    }

    public function test_check_start_partial(): void
    {
        $this->transactionService->addStock($this->rawMaterial->id, $existed = 10, $this->warehouse->id);

        $insufficientAssetsByCat = $this->prodOrderService->checkStart($this->prodOrder);
        $insufficientAssets = $insufficientAssetsByCat[$this->productCategory->id];

        $this->assertNotEmpty($insufficientAssets);
        $this->assertCount(2, $insufficientAssets);

        $firstStep = $this->prodTemplate->firstStep;
        foreach ($insufficientAssets as $productId => $item) {
            /** @var ProdOrderStepProduct $stepMaterial */
            $stepMaterial = $firstStep->materials()->where('product_id', $productId)->first();
            $expected = $stepMaterial->required_quantity * $this->prodOrder->quantity;
            if ($productId === $this->rawMaterial->id) {
                $expected -= $existed;
            }
            $this->assertEquals($expected, $item['quantity']);
        }
    }
}
