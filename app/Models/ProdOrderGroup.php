<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use App\Enums\ProdOrderGroupType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property ProdOrderGroupType $type
 * @property int $warehouse_id
 * @property int $organization_id
 * @property Carbon $deadline
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Warehouse $warehouse
 * @property Organization $organization
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prodOrders(): HasMany
    {
        return $this->hasMany(ProdOrder::class, 'group_id');
    }
}
