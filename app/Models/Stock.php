<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property int $work_station_id
 * @property int $warehouse_id
 * @property int $storage_location_id
 * @property StockStatus $status
 *
 * @property-read Product $product
 * @property-read WorkStation $workStation
 * @property-read Warehouse $warehouse
 * @property-read StorageLocation $storageLocation
 */
class Stock extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => StockStatus::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }
}
