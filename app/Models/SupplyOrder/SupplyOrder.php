<?php

namespace App\Models\SupplyOrder;

use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Models\Organization;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProductCategory;
use App\Models\Scopes\OwnWarehouseScope;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $supplier_organization_id
 * @property int $prod_order_id
 * @property int $product_category_id
 * @property int $total_price
 * @property int $warehouse_id
 * @property SupplyOrderState $state
 * @property string $status
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Carbon $confirmed_at
 * @property int $confirmed_by
 * @property Carbon $progressed_at
 * @property int $progressed_by
 * @property Carbon $delivered_at
 * @property int $delivered_by
 * @property Carbon $closed_at
 * @property int $closed_by
 *
 * @property User $confirmedBy
 * @property User $progressedBy
 * @property User $deliveredBy
 * @property User $closedBy
 *
 * Relationships
 * @property Organization $supplierOrganization
 * @property ProdOrder $prodOrder
 * @property Warehouse $warehouse
 * @property ProductCategory $productCategory
 * @property User $createdBy
 * @property Collection<SupplyOrderStep> $steps
 * @property Collection<SupplyOrderLocation> $locations
 * @property Collection<SupplyOrderProduct> $products
 */
#[ScopedBy(OwnWarehouseScope::class)]
class SupplyOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'state' => SupplyOrderState::class,
//        'status' => SupplyOrderStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'progressed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplyOrder $model) {
            $model->created_by = auth()->id();
            if ($model->supplierOrganization) {
                $model->number = 'SO-' . $model->supplierOrganization->code . $model->productCategory->code . now()->format('dmy');
            }
        });
        static::updating(function (SupplyOrder $model) {
            $model->created_by = auth()->id();
            if ($model->supplierOrganization) {
                $model->number = 'SO-' . $model->supplierOrganization->code . $model->productCategory->code . now()->format('dmy');
            }
        });
    }

    public function supplierOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'supplier_organization_id');
    }

    public function prodOrder(): BelongsTo
    {
        return $this->belongsTo(ProdOrder::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function progressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'progressed_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SupplyOrderStep::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SupplyOrderLocation::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplyOrderProduct::class);
    }

    public function getTotalPriceAttribute(): int
    {
        return $this->products->sum('price');
    }

    public function setStatus(SupplyOrderState $state, ?string $status = null): void
    {
        $changed = $this->state?->value != $state->value || $this->status != $status;
        if ($changed) {
            $this->steps()->create([
                'state' => $state,
                'status' => $status,
                'created_by' => auth()->user()->id,
                'created_at' => now(),
            ]);
        }

        $this->state = $state;
        $this->status = $status;
    }

    public function updateStatus(SupplyOrderState $state, ?string $status = null): void
    {
        $this->setStatus($state, $status);
        $this->save();
    }

    public function hasStatus(SupplyOrderState $state, string $status): bool
    {
        return $this->state->value == $state->value && $this->status == $status;
    }

    public function getStatus(): string
    {
        $state = $this->state->getLabel();
        $statusEnum = SupplyOrderStatus::tryFrom($this->status);
        $status = $statusEnum ? $statusEnum->getLabel() : $this->status;
        if (empty($status)) {
            return $state;
        }
        return "$state: $status";
    }

    public function isConfirmed(): bool
    {
        return !!$this->confirmed_at;
    }

    public function confirm(): void
    {
        if (!$this->confirmed_at) {
            $this->update([
                'confirmed_at' => now(),
                'confirmed_by' => auth()->user()->id,
            ]);
        }
    }
}
