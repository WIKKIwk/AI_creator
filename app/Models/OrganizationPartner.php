<?php

namespace App\Models;

use App\Enums\PartnerType;
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
 * @property int $organization_id
 * @property int $partner_id
 * @property PartnerType $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Organization $organization
 * @property-read Organization $partner
 * @property-read Collection<OrganizationPartnerProduct> $products
 */
#[ScopedBy(OwnOrganizationScope::class)]
class OrganizationPartner extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => PartnerType::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeAgent($query)
    {
        return $query->where('type', PartnerType::Agent);
    }

    public function scopeSupplier($query)
    {
        return $query->where('type', PartnerType::Supplier);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'partner_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(OrganizationPartnerProduct::class, 'organization_partner_id');
    }
}
