<?php

namespace App\Models;

use Carbon\Carbon;
use App\Enums\MeasureUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $code
 * @property int $parent_id
 * @property int $organization_id
 * @property MeasureUnit $measure_unit
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read ProductCategory $parent
 * @property-read Organization $organization
 * @property-read Collection<ProductCategory> $children
 */
class ProductCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'measure_unit' => MeasureUnit::class,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }
}
