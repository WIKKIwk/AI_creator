<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $agent_id
 * @property int $quantity
 * @property OrderStatus $status
 * @property int $offer_price
 * @property int $total_cost
 * @property int $deadline
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Agent $agent
 * @property Product $product
 */
class ProdOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => OrderStatus::class,
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
