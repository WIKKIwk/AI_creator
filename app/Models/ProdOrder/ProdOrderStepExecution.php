<?php

namespace App\Models\ProdOrder;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $prod_order_step_id
 * @property float $output_quantity
 * @property string $notes
 * @property int $executed_by
 *
 * @property Carbon $approved_at_prod_manager_id
 * @property int $approved_by_prod_manager_id
 *
 * @property Carbon $approved_at_prod_senior_manager_id
 * @property int $approved_by_prod_senior_manager_id
 *
 * @property Carbon $approved_at
 * @property int $approved_by
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ProdOrderStep $prodOrderStep
 * @property User $executedBy
 * @property User $approvedByProdManager
 * @property User $approvedByProdSeniorManager
 * @property User $approvedBy
 * @property Collection<ProdOrderStepExecutionProduct> $materials
 */
class ProdOrderStepExecution extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at_prod_manager_id' => 'datetime',
        'approved_at_prod_senior_manager_id' => 'datetime',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProdOrderStepExecution $execution) {
            $execution->executed_by = auth()->user()->id;
        });
    }

    public function getApprovedField(): string
    {
        return match (auth()->user()->role) {
            RoleType::SENIOR_PRODUCTION_MANAGER => 'approved_at_prod_senior_manager_id',
            RoleType::PRODUCTION_MANAGER => 'approved_at_prod_manager_id',
            default => 'approved_at',
        };
    }

    public function getApprovedByField(): string
    {
        return match (auth()->user()->role) {
            RoleType::SENIOR_PRODUCTION_MANAGER => 'approved_by_prod_senior_manager_id',
            RoleType::PRODUCTION_MANAGER => 'approved_by_prod_manager_id',
            default => 'approved_by',
        };
    }

    public function prodOrderStep(): BelongsTo
    {
        return $this->belongsTo(ProdOrderStep::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function approvedByProdManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_prod_manager_id');
    }

    public function approvedByProdSeniorManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_prod_senior_manager_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProdOrderStepExecutionProduct::class);
    }
}
