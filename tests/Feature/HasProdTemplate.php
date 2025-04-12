<?php

namespace Tests\Feature;

use App\Enums\StepProductType;
use App\Models\ProdTemplate;
use App\Models\ProdTemplateStep;
use App\Models\Product;
use App\Models\WorkStation;

trait HasProdTemplate
{
    protected Product $rawMaterial;
    protected Product $semiFinishedMaterial;
    protected Product $readyProduct;
    protected WorkStation $workStationFirst;
    protected WorkStation $workStationSecond;

    public function createProdTemplate(): void
    {
        $this->workStationFirst = WorkStation::factory()->create(['name' => 'First Work Station']);
        $this->workStationSecond = WorkStation::factory()->create(['name' => 'Second Work Station']);

        $this->rawMaterial = $this->createProduct(['name' => 'Raw Material']);
        $this->semiFinishedMaterial = $this->createProduct(['name' => 'Semi Finished Product']);
        $this->readyProduct = $this->createProduct(['name' => 'Ready Product']);

        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->create([
            'name' => 'Test Template',
            'product_id' => $this->readyProduct->id,
        ]);
        /** @var ProdTemplateStep $stepFirstTemplate */
        $stepFirstTemplate = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id
        ]);
        $stepFirstTemplate->productItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => 1,
            'type' => StepProductType::Required
        ]);
        $stepFirstTemplate->productItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 1,
            'type' => StepProductType::Expected
        ]);
        /** @var ProdTemplateStep $stepSecondTemplate */
        $stepSecondTemplate = $prodTemplate->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id
        ]);
        $stepSecondTemplate->productItems()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'quantity' => 1,
            'type' => StepProductType::Required
        ]);
        $stepSecondTemplate->productItems()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => 1,
            'type' => StepProductType::Expected
        ]);
    }
}
