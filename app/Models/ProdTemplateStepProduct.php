<?php

namespace App\Models;

use App\Enums\StepProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prod_template_station_id
 * @property int $product_id
 * @property float $quantity
 * @property StepProductType $type
 *
 * @property ProdTemplateStep $step
 * @property Product $product
 */
class ProdTemplateStepProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => StepProductType::class,
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProdTemplateStep::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
