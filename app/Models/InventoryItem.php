<?php

namespace App\Models;

use App\Models\Scopes\OwnWarehouseScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_id
 * @property int $quantity
 * @property int $storage_location_id
 * @property int $storage_floor
 *
 * @property-read Inventory $inventory
 * @property-read StorageLocation $storageLocation
 */
class InventoryItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }
}
