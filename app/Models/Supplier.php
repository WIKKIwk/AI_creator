<?php

namespace App\Models;

use App\Enums\PartnerType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $code
 * @property PartnerType $type
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Collection<SupplierProduct> $products
 */
class Supplier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => PartnerType::class,
    ];

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
