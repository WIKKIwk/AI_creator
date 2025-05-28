<?php

namespace App\Models\Inventory;

use App\Enums\InventoryStatus;
use App\Models\Product;
use App\Models\WorkStation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $work_station_id
 * @property int $product_id
 * @property float $quantity
 * @property float $unit_cost
 * @property InventoryStatus $status
 *
 * @property Product $product
 * @property WorkStation $workStation
 */
class MiniInventory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => InventoryStatus::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }
}
