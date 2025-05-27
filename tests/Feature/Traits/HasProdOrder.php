<?php

namespace Tests\Feature\Traits;

use App\Models\Inventory;
use App\Models\Product;

trait HasProdOrder
{
    public function getInventory(Product $product): ?Inventory
    {
        /** @var Inventory $inventory */
        $inventory = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        return $inventory;
    }
}
