<?php

namespace App\Models;

use App\Enums\ProdOrderProductStatus;
use App\Enums\StepProductType;
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
 * @property ProdOrderProductStatus $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ProdOrder $prodOrder
 * @property WorkStation $workStation
 * @property Collection<ProdOrderStepProduct> $productItems
 * @property Collection<ProdOrderStepProduct> $requiredItems
 * @property Collection<ProdOrderStepProduct> $expectedItems
 * @property Collection<ProdOrderStepProduct> $actualItems
 */
class ProdOrderStep extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ProdOrderProductStatus::class,
    ];

    public function prodOrder(): BelongsTo
    {
        return $this->belongsTo(ProdOrder::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function productItems(): HasMany
    {
        return $this->hasMany(ProdOrderStepProduct::class);
    }

    public function requiredItems(): HasMany
    {
        return $this->hasMany(ProdOrderStepProduct::class)
            ->where('type', StepProductType::Required)
            ->orderBy('created_at');
    }

    public function expectedItems(): HasMany
    {
        return $this->hasMany(ProdOrderStepProduct::class)
            ->where('type', StepProductType::Expected)
            ->orderBy('created_at');
    }

    public function actualItems(): HasMany
    {
        return $this->hasMany(ProdOrderStepProduct::class)
            ->where('type', StepProductType::Actual)
            ->orderBy('created_at');
    }
}
