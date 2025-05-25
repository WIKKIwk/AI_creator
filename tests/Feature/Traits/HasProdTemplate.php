<?php

namespace Tests\Feature\Traits;

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
            'organization_id' => $this->organization->id,
        ]);

        /** @var ProdTemplateStep $firstStep */
        $firstStep = $prodTemplate->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $this->semiFinishedMaterial->id,
            'expected_quantity' => 1,
        ]);
        $firstStep->materials()->create([
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => 1
        ]);

        /** @var ProdTemplateStep $secondStep */
        $secondStep = $prodTemplate->steps()->create([
            'sequence' => 2,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $this->readyProduct->id,
            'expected_quantity' => 1,
        ]);
        $secondStep->materials()->create([
            'product_id' => $this->semiFinishedMaterial->id,
            'required_quantity' => 1
        ]);
    }
}
