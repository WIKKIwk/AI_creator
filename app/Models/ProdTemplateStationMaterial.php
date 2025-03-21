<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prod_template_station_id
 * @property int $material_product_id
 * @property float $quantity
 *
 * @property ProdTemplateStation $station
 * @property Product $materialProduct
 */
class ProdTemplateStationMaterial extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function station(): BelongsTo
    {
        return $this->belongsTo(ProdTemplateStation::class);
    }

    public function materialProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
