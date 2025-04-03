<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property float $quantity
 * @property float $unit_cost
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 * @property-read Collection<InventoryItem> $items
 */
class Inventory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function getQuantityAttribute(): int
    {
        return $this->items->sum('quantity');
    }
}
