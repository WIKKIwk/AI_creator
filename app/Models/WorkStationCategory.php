<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $short_code
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Collection<WorkStation> $workStations
 */
class WorkStationCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function workStations(): HasMany
    {
        return $this->hasMany(WorkStation::class);
    }
}
