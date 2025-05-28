<?php

namespace App\Models\Inventory;

use App\Enums\TransactionType;
use App\Models\Agent;
use App\Models\ProdOrder\ProdOrder;
use App\Models\Product;
use App\Models\Scopes\OwnWarehouseScope;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WorkStation;
use App\Observers\InventoryTransactionObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $work_station_id
 * @property int $supplier_id
 * @property int $agent_id
 * @property int $storage_location_id
 * @property int $prod_order_id
 * @property float $quantity
 * @property float $cost
 * @property TransactionType $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * Relationships
 * @property-read Supplier $supplier
 * @property-read ProdOrder $prodOrder
 * @property-read Agent $agent
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 * @property-read WorkStation $workStation
 * @property-read StorageLocation $storage_location
 *
 */
#[ScopedBy(OwnWarehouseScope::class)]
class InventoryTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => TransactionType::class,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prodOrder(): BelongsTo
    {
        return $this->belongsTo(ProdOrder::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }
}
