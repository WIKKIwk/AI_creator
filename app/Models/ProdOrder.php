<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Services\ProdOrderService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $product_id
 * @property int $agent_id
 * @property int $warehouse_id
 * @property int $quantity
 * @property OrderStatus $status
 * @property int $offer_price
 * @property int $total_cost
 * @property int $deadline
 * @property int $current_step_id
 * @property bool $can_produce
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Warehouse $warehouse
 * @property Agent $agent
 * @property Product $product
 * @property Collection<ProdOrderStep> $steps
 * @property ProdOrderStep $firstStep
 * @property ProdOrderStep $currentStep
 */
class ProdOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => OrderStatus::class,
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProdOrderStep::class)->orderBy('sequence');
    }

    public function firstStep(): HasOne
    {
        return $this->hasOne(ProdOrderStep::class)->orderBy('sequence');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStep::class, 'current_step_id');
    }
}
