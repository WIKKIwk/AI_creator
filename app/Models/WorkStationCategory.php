<?php

namespace App\Models;

use App\Models\Scopes\OwnOrganizationScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $organization_id
 * @property string|null $short_code
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Organization $organization
 * @property-read Collection<WorkStation> $workStations
 */
#[ScopedBy(OwnOrganizationScope::class)]
class WorkStationCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workStations(): HasMany
    {
        return $this->hasMany(WorkStation::class);
    }
}
