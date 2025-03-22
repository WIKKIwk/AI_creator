<?php

namespace App\Models;

use App\Enums\MeasureUnit;
use App\Enums\ProductType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property float $price
 * @property ProductType $type
 * @property MeasureUnit $measure_unit
 * @property int $product_category_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read ProductCategory $category
 * @property-read Collection<Inventory> $inventories
 */
class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => ProductType::class,
        'measure_unit' => MeasureUnit::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function getInventory($warehouseId): Inventory
    {
        /** @var Inventory $inventory */
        $inventory = Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $this->id)
            ->firstOrFail();

        return $inventory;
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
