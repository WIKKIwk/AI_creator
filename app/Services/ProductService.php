<?php

namespace App\Services;

use App\Models\Product;
use App\Enums\ProductType;
use App\Models\WorkStation;
use App\Models\ProdTemplate;

class ProductService
{
    public function createOrGetSemiFinished(ProdTemplate $prodTmp, $workStationId, $isLast = false): ?Product
    {
        if ($isLast) {
            return $prodTmp->product;
        }

        $outputProduct = null;
        /** @var WorkStation $workStation */
        $workStation = $workStationId ? WorkStation::query()->find($workStationId) : null;

        if ($workStation && $prodTmp->product) {
            $productName = "$workStation->name {$prodTmp->product->name} SFP";

            /** @var Product $outputProduct */
            $outputProduct = Product::query()->firstOrCreate(
                ['name' => $productName],
                [
                    'type' => ProductType::SemiFinishedProduct,
                    'product_category_id' => $prodTmp->product->product_category_id,
//                    'product_category_id' => $workStation->product_category_id,
                    'work_station_id' => $workStation->id,
                    'ready_product_id' => $prodTmp->product->id
                ]
            );
        }

        return $outputProduct;
    }
}
