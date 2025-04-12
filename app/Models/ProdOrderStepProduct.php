<?php

namespace App\Models;

use App\Enums\ProdOrderProductStatus;
use App\Enums\ProdOrderProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prod_order_step_id
 * @property int $product_id
 * @property float $quantity
 * @property float $max_quantity
 * @property ProdOrderProductType $type
 * @property ProdOrderProductStatus $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ProdOrderStep $prodOrderStep
 * @property Product $product
 */
class ProdOrderStepProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => ProdOrderProductType::class,
        'status' => ProdOrderProductStatus::class,
    ];

    public function prodOrderStep(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStep::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}
