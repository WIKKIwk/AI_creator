<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $number
 * @property string|null $floor
 * @property string|null $description
 * @property int $work_station_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read WorkStation $workStation
 * @property-read Collection<Stock> $stocks
 */
class StorageLocation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
