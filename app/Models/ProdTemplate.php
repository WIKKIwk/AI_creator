<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property int $product_id
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Product $product
 * @property Collection<ProdTemplateStep> $steps
 * @property Collection<WorkStation> $workStations
 */
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
        return $this->hasMany(ProdTemplateStep::class, 'prod_template_id');
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
}
