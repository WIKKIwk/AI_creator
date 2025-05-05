<?php

namespace App\Services;

use App\Models\Product;
use App\Enums\ProductType;
use App\Models\WorkStation;
use App\Models\ProdTemplate;

class ProductService
{
    public function createSemiFinished(ProdTemplate $pt, $wstId): ?Product
    {
        $outputProduct = null;

        /** @var WorkStation $workStation */
        $workStation = $wstId ? WorkStation::find($wstId) : null;

        if ($workStation && $pt->product) {
            $productName = "$workStation->name {$pt->product->name} SFP";

            /** @var Product $outputProduct */
            $outputProduct = Product::query()->firstOrCreate(
                ['name' => $productName],
                [
                    'type' => ProductType::SemiFinishedProduct,
                    'product_category_id' => $workStation->product_category_id,
                    'work_station_id' => $workStation->id,
                    'ready_product_id' => $pt->product->id
                ]
            );
        }

        return $outputProduct;
    }
}
