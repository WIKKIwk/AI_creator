<?php

namespace App\Models\SupplyOrder;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supply_order_id
 * @property string $location
 * @property int $created_by
 * @property Carbon $created_at
 *
 * @property-read SupplyOrder $supplyOrder
 * @property-read User $createdBy
 */
class SupplyOrderLocation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $supplyOrderLocation) {
            $supplyOrderLocation->created_at = now();
            $supplyOrderLocation->created_by = auth()->id();
        });
    }

    public function supplyOrder(): BelongsTo
    {
        return $this->belongsTo(SupplyOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
