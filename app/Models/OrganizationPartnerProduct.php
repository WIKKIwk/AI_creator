<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_partner_id
 * @property int $product_id
 * @property float $price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read OrganizationPartner $organizationPartner
 * @property-read Product $product
 */
class OrganizationPartnerProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organizationPartner(): BelongsTo
    {
        return $this->belongsTo(OrganizationPartner::class, 'organization_partner_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
