<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $prod_template_id
 * @property int $work_station_id
 * @property int $sequence
 *
 * @property ProdTemplate $prodTemplate
 * @property WorkStation $workStation
 * @property Collection<ProdTemplateStationMaterial> $materials
 */
class ProdTemplateStation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function prodTemplate(): BelongsTo
    {
        return $this->belongsTo(ProdTemplate::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProdTemplateStationMaterial::class);
    }
}
