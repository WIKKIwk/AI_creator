<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supply_order_id
 * @property int $product_id
 * @property double $expected_quantity
 * @property double $actual_quantity
 *
 * @property SupplyOrder $supplyOrder
 * @property Product $product
 */
class SupplyOrderProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function supplyOrder(): BelongsTo
    {
        return $this->belongsTo(SupplyOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
