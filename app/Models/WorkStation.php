<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $product_category_id
 * @property int $type
 * @property int $organization_id
 * @property int $prod_order_id
 *
 * @property ProdOrder $prodOrder
 * @property ProductCategory $productCategory
 * @property Organization $organization
 * @property Collection<PerformanceRate> $performanceRates
 * @property Collection<MiniInventory> $miniInventories
 */
class WorkStation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function performanceRates(): HasMany
    {
        return $this->hasMany(PerformanceRate::class);
    }

    public function miniInventories(): HasMany
    {
        return $this->hasMany(MiniInventory::class);
    }

    public function prodOrder(): BelongsTo
    {
        return $this->belongsTo(ProdOrder::class);
    }
}
