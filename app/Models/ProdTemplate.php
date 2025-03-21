<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $product_id
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Product $product
 * @property Collection<ProdTemplateStation> $stations
 */
class ProdTemplate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(ProdTemplateStation::class, 'prod_template_id');
    }
}
