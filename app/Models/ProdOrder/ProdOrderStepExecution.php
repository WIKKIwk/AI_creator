<?php

namespace App\Models\ProdOrder;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $prod_order_step_id
 * @property float $output_quantity
 * @property string $notes
 * @property int $executed_by
 *
 * @property ProdOrderStep $prodOrderStep
 * @property User $executedBy
 * @property Collection<ProdOrderStepExecutionProduct> $materials
 */
class ProdOrderStepExecution extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function prodOrderStep(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStep::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProdOrderStepExecutionProduct::class);
    }
}
