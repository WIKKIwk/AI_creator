<?php

namespace App\Models\ProdTemplate;

use App\Models\Organization;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\Product;
use App\Models\Scopes\OwnOrganizationScope;
use App\Models\WorkStation;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property int $product_id
 * @property int $organization_id
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Product $product
 * @property ProdOrderStep $firstStep
 * @property ProdOrderStep $lastStep
 * @property Collection<ProdTemplateStep> $steps
 * @property Collection<WorkStation> $workStations
 */
#[ScopedBy(OwnOrganizationScope::class)]
class ProdTemplate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProdTemplateStep::class, 'prod_template_id')->orderBy('sequence');
    }

    public function firstStep(): HasOne
    {
        return $this->hasOne(ProdTemplateStep::class)->orderBy('sequence');
    }

    public function lastStep(): HasOne
    {
        return $this->hasOne(ProdTemplateStep::class)->orderBy('sequence', 'desc');
    }

    public function workStations(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorkStation::class,
            ProdTemplateStep::class,
            'prod_template_id',
            'id',
            'id',
            'work_station_id'
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
