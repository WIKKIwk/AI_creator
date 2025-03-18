<?php

namespace App\Models;

use App\Enums\DurationUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property integer $id
 * @property integer $work_station_id
 * @property integer $product_id
 * @property integer $quantity
 * @property integer $duration
 * @property DurationUnit $duration_unit
 * @property string $created_at
 * @property string $updated_at
 *
 * @property WorkStation $workStation
 * @property Product $product
 */
class PerformanceRate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'duration_unit' => DurationUnit::class,
    ];

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
