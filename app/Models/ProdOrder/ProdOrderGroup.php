<?php

namespace App\Models\ProdOrder;

use App\Enums\PartnerType;
use App\Enums\ProdOrderGroupType;
use App\Models\Organization;
use App\Models\OrganizationPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ProdOrderGroupType $type
 * @property int $warehouse_id
 * @property int $agent_id
 * @property Carbon $deadline
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Warehouse $warehouse
 * @property OrganizationPartner $agent
 * @property User $createdBy
 * @property Collection<ProdOrder> $prodOrders
 */
class ProdOrderGroup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => ProdOrderGroupType::class,
        'deadline' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(OrganizationPartner::class, 'agent_id')->agent()->with('partner');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prodOrders(): HasMany
    {
        return $this->hasMany(ProdOrder::class, 'group_id');
    }

    public function getProgress(): float
    {
        $totalProgress = 0;
        foreach ($this->prodOrders as $prodOrder) {
            $totalProgress += $prodOrder->getProgress();
        }

        return $totalProgress / max($this->prodOrders->count(), 1);
    }

    public function isConfirmed(): bool
    {
        if ($this->prodOrders->isEmpty()) {
            return false;
        }

        return $this->prodOrders->every(fn(ProdOrder $order) => $order->isConfirmed());
    }

    public function confirm(): void
    {
        foreach ($this->prodOrders as $prodOrder) {
            $prodOrder->confirm();
        }
    }
}
