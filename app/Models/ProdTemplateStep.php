<?php

namespace App\Models;

use App\Enums\StepProductType;
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
 * @property int $output_quantity
 *
 * @property ProdTemplate $prodTemplate
 * @property Product $outputProduct
 * @property WorkStation $workStation
 * @property Collection<ProdTemplateStepProduct> $productItems
 * @property Collection<ProdTemplateStepProduct> $requiredItems
 * @property Collection<ProdTemplateStepProduct> $expectedItems
 */
class ProdTemplateStep extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

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

    public function productItems(): HasMany
    {
        return $this->hasMany(ProdTemplateStepProduct::class);
    }

    public function requiredItems(): HasMany
    {
        return $this->hasMany(ProdTemplateStepProduct::class)->where('type', StepProductType::Required);
    }

    public function expectedItems(): HasMany
    {
        return $this->hasMany(ProdTemplateStepProduct::class)->where('type', StepProductType::Expected);
    }

    public function actualItems(): HasMany
    {
        return $this->hasMany(ProdTemplateStepProduct::class)->where('type', StepProductType::Actual);
    }
}
