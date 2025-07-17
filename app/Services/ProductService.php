<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\Product;
use App\Models\WorkStation;

class ProductService
{
    public static function getSfpName(Product $product, WorkStation $workStation): string
    {
        return "$workStation->name {$product->name} YTM";
    }

    public function createOrGetSemiFinished(ProdTemplate $prodTmp, $workStationId, $isLast = false): ?Product
    {
        if ($isLast) {
            return $prodTmp->product;
        }

        $outputProduct = null;
        /** @var WorkStation $workStation */
        $workStation = $workStationId ? WorkStation::query()->find($workStationId) : null;

        if ($workStation && $prodTmp->product) {
            /** @var Product $outputProduct */
            $outputProduct = Product::query()->firstOrCreate(
                ['name' => ProductService::getSfpName($prodTmp->product, $workStation)],
                [
                    'type' => ProductType::SemiFinishedProduct,
                    // TODO: figure out with measure_unit of step to SFP product's category
                    'product_category_id' => $prodTmp->product->product_category_id,
                    'work_station_id' => $workStation->id,
                    'ready_product_id' => $prodTmp->product->id
                ]
            );
        }

        return $outputProduct;
    }
}
