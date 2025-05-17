<?php

namespace App\Models;

use App\Models\Scopes\OwnOrganizationScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $address
 * @property int $organization_id
 *
 * @property Organization $organization
 * @property Collection<StorageLocation> $locations
 */
#[ScopedBy(OwnOrganizationScope::class)]
class Warehouse extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StorageLocation::class);
    }
}
