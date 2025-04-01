<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $prod_order_id
 * @property int $product_id
 * @property OrderStatus $status
 * @property int $quantity
 * @property int $total_price
 * @property int $warehouse_id
 * @property int $unit_price
 * @property int $created_by
 * @property int $created_at
 * @property int $updated_at
 *
 * Relationships
 * @property Supplier $supplier
 * @property ProdOrder $prodOrder
 * @property Warehouse $warehouse
 * @property Product $product
 * @property User $createdBy
 */
class SupplyOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => OrderStatus::class,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prodOrder(): BelongsTo
    {
        return $this->belongsTo(ProdOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
