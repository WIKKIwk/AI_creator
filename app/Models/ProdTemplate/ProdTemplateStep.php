<?php

namespace App\Models\ProdTemplate;

use App\Enums\MeasureUnit;
use App\Models\Product;
use App\Models\WorkStation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $prod_template_id
 * @property int $work_station_id
 * @property int $sequence
 * @property int $output_product_id
 * @property int $expected_quantity
 * @property MeasureUnit $measure_unit
 * @property bool $is_last
 *
 * @property ProdTemplate $prodTemplate
 * @property Product $outputProduct
 * @property WorkStation $workStation
 * @property Collection<ProdTemplateStepProduct> $materials
 */
class ProdTemplateStep extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'measure_unit' => MeasureUnit::class,
    ];

    public function prodTemplate(): BelongsTo
    {
        return $this->belongsTo(ProdTemplate::class);
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProdTemplateStepProduct::class);
    }
}
