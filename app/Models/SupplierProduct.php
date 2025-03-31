<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $product_id
 * @property int $unit_price
 * @property int $currency
 * @property int $type
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Supplier $supplier
 * @property Product $product
 */
class SupplierProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
