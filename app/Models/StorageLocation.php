<?php

namespace App\Models;

use App\Models\Scopes\OwnWarehouseScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $number
 * @property string|null $floors_count
 * @property string|null $description
 * @property int $warehouse_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Warehouse $warehouse
 */
#[ScopedBy(OwnWarehouseScope::class)]
class StorageLocation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
