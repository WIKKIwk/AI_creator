<?php

namespace App\Models\ProdOrder;

use App\Enums\ProdOrderStepProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prod_order_step_id
 * @property int $product_id
 * @property float $required_quantity
 * @property float $available_quantity
 * @property float $used_quantity
 * @property ProdOrderStepProductStatus $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ProdOrderStep $step
 * @property Product $product
 */
class ProdOrderStepProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ProdOrderStepProductStatus::class,
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStep::class, 'prod_order_step_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}
