<?php

namespace App\Models\ProdOrder;

use App\Enums\ProdOrderStepStatus;
use App\Models\Product;
use App\Models\WorkStation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $prod_order_id
 * @property int $work_station_id
 * @property int $sequence
 * @property ProdOrderStepStatus $status
 * @property int $output_product_id
 * @property int $expected_quantity
 * @property int $output_quantity
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ProdOrder $prodOrder
 * @property Product $outputProduct
 * @property WorkStation $workStation
 * @property Collection<ProdOrderStepProduct> $materials
 * @property Collection<ProdOrderStepExecution> $executions
 */
class ProdOrderStep extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ProdOrderStepStatus::class,
    ];

    public function prodOrder(): BelongsTo
    {
        return $this->belongsTo(ProdOrder::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProdOrderStepProduct::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ProdOrderStepExecution::class);
    }
}
