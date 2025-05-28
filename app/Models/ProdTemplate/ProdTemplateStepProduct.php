<?php

namespace App\Models\ProdTemplate;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prod_template_step_id
 * @property int $product_id
 * @property float $required_quantity
 *
 * @property ProdTemplateStep $step
 * @property Product $product
 */
class ProdTemplateStepProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProdTemplateStep::class, 'prod_template_step_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
