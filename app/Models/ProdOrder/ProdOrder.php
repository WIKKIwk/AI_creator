<?php

namespace App\Models\ProdOrder;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use App\Observers\ProdOrderObserver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $group_id
 * @property int $product_id
 * @property int $quantity
 * @property OrderStatus $status
 * @property int $offer_price
 * @property int $total_cost
 * @property int $actual_cost
 * @property int $deadline
 * @property int $actual_deadline
 * @property int $current_step_id
 * @property bool $can_produce
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Carbon $started_at
 * @property Carbon $approved_at
 * @property Carbon $confirmed_at
 * @property int $started_by
 * @property int $approved_by
 * @property int $confirmed_by
 *
 * @property User $startedBy
 * @property User $approvedBy
 * @property User $confirmedBy
 *
 * @property ProdOrderGroup $group
 * @property Product $product
 * @property Collection<ProdOrderStep> $steps
 * @property ProdOrderStep $firstStep
 * @property ProdOrderStep $lastStep
 * @property ProdOrderStep $currentStep
 * @property Collection<SupplyOrder> $supplyOrders
 */
class ProdOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => OrderStatus::class,
        'started_at' => 'datetime',
        'approved_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (ProdOrder $model) {
            $model->number = 'PO-' . $model->group->organization->code . $model->product->code . now()->format('dmy');
        });
        static::updating(function (ProdOrder $model) {
            $model->number = 'PO-' . $model->group->organization->code . $model->product->code . now()->format('dmy');
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProdOrderGroup::class, 'group_id');
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

    public function lastStep(): HasOne
    {
        return $this->hasOne(ProdOrderStep::class)->orderBy('sequence', 'desc');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStep::class, 'current_step_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function supplyOrders(): HasMany
    {
        return $this->hasMany(SupplyOrder::class, 'prod_order_id');
    }

    public function confirm(): void
    {
        $this->update([
            'confirmed_at' => now(),
            'confirmed_by' => auth()->user()->id,
        ]);
    }

    public function getWarehouseId(): int
    {
        return $this->group->warehouse_id;
    }

    public function getProgress(): float
    {
        /** @var ProdOrderStep $lastStep */
        $lastStep = $this->steps->last();
        return round(($lastStep->output_quantity / $this->quantity) * 100);
    }
}
