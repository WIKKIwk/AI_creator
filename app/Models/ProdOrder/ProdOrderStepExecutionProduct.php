<?php

namespace App\Models\ProdOrder;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prod_order_step_execution_id
 * @property int $product_id
 * @property float $used_quantity
 *
 * @property ProdOrderStepExecution $prodOrderStepExecution
 * @property Product $product
 */
class ProdOrderStepExecutionProduct extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function prodOrderStepExecution(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStepExecution::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
