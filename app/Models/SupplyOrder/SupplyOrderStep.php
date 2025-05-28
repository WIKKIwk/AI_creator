<?php

namespace App\Models\SupplyOrder;

use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supply_order_id
 * @property SupplyOrderState $state
 * @property string $status
 * @property int $created_by
 * @property Carbon $created_at
 *
 * @property-read SupplyOrder $supplyOrder
 * @property-read User $createdBy
 */
class SupplyOrderStep extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'state' => SupplyOrderState::class,
        'created_at' => 'datetime',
    ];

    public function supplyOrder(): BelongsTo
    {
        return $this->belongsTo(SupplyOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
}
