<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Observers\InventoryTransactionObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $storage_location_id
 * @property float $quantity
 * @property float $cost
 * @property TransactionType $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 * @property-read StorageLocation $storage_location
 *
 */
#[ObservedBy(InventoryTransactionObserver::class)]
class InventoryTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => TransactionType::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
