<?php

namespace Tests\Feature\Traits;

use App\Models\SupplyOrder\SupplyOrder;

trait HasSupplyOrder
{
    public function createSupplyOrder(array $data = []): SupplyOrder
    {
        return SupplyOrder::factory()->create(array_merge([
            'warehouse_id' => $this->warehouse->id,
            'supplier_organization_id' => $this->organization2->id,
            'product_category_id' => $this->productCategory->id,
            'created_by' => $this->user->id,
        ], $data));
    }
}
