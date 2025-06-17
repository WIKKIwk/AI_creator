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
 * @property Carbon $approved_at_prod_manager
 * @property int $approved_by_prod_manager
 * @property Carbon $declined_at_prod_manager
 * @property int $declined_by_prod_manager
 * @property string $decline_comment_prod_manager
 *
 * @property Carbon $approved_at_prod_senior_manager
 * @property int $approved_by_prod_senior_manager
 * @property Carbon $declined_at_senior_prod_manager
 * @property int $declined_by_senior_prod_manager
 * @property string $decline_comment_senior_prod_manager
 *
 * @property Carbon $approved_at
 * @property int $approved_by
 * @property Carbon $declined_at
 * @property int $declined_by
 * @property string $decline_comment
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ProdOrderStep $prodOrderStep
 * @property User $executedBy
 * @property User $approvedByProdManager
 * @property User $approvedByProdSeniorManager
 * @property User $approvedBy
 * @property User $declinedByProdManager
 * @property User $declinedByProdSeniorManager
 * @property User $declinedBy
 * @property Collection<ProdOrderStepExecutionProduct> $materials
 */
class ProdOrderStepExecution extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at_prod_manager' => 'datetime',
        'approved_at_prod_senior_manager' => 'datetime',
        'approved_at' => 'datetime',
        'declined_at_prod_manager' => 'datetime',
        'declined_at_senior_prod_manager' => 'datetime',
        'declined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProdOrderStepExecution $execution) {
            $execution->executed_by = auth()->user()->id;
        });
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
        return $this->belongsTo(User::class, 'approved_by_prod_manager');
    }

    public function approvedByProdSeniorManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_prod_senior_manager');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function declinedByProdManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by_prod_manager');
    }

    public function declinedByProdSeniorManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by_senior_prod_manager');
    }

    public function declinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProdOrderStepExecutionProduct::class);
    }

    public function getPrevApprovedUser(RoleType $role): ?User
    {
        return match ($role) {
            RoleType::STOCK_MANAGER => $this->approvedByProdSeniorManager,
            RoleType::SENIOR_PRODUCTION_MANAGER => $this->approvedByProdManager,
            RoleType::PRODUCTION_MANAGER => $this->executedBy,
            default => null
        };
    }

    public function getNextRole(RoleType $role): ?RoleType
    {
        return match ($role) {
            RoleType::WORK_STATION_WORKER => RoleType::PRODUCTION_MANAGER,
            RoleType::PRODUCTION_MANAGER => RoleType::SENIOR_PRODUCTION_MANAGER,
            RoleType::SENIOR_PRODUCTION_MANAGER => RoleType::STOCK_MANAGER,
            default => null, // No next role
        };
    }

    public function approve(): void
    {
        /** @var User $user */
        $user = auth()->user();

        switch ($user->role) {
            case RoleType::PRODUCTION_MANAGER:
                $this->update([
                    'approved_at_prod_manager' => now(),
                    'approved_by_prod_manager' => $user->id,
                    'declined_at_prod_manager' => null,
                    'declined_by_prod_manager' => null,
                    'decline_comment_prod_manager' => null,
                ]);
                break;
            case RoleType::SENIOR_PRODUCTION_MANAGER:
                $this->update([
                    'approved_at_prod_senior_manager' => now(),
                    'approved_by_prod_senior_manager' => $user->id,
                    'declined_at_senior_prod_manager' => null,
                    'declined_by_senior_prod_manager' => null,
                    'decline_comment_senior_prod_manager' => null,
                ]);
                break;
            case RoleType::STOCK_MANAGER:
                $this->update([
                    'approved_at' => now(),
                    'approved_by' => $user->id,
                    'declined_at' => null,
                    'declined_by' => null,
                    'decline_comment' => null,
                ]);
                break;
        }
    }

    public function decline(string $comment): void
    {
        /** @var User $user */
        $user = auth()->user();

        switch ($user->role) {
            case RoleType::PRODUCTION_MANAGER:
                $this->update([
                    'declined_at_prod_manager' => now(),
                    'declined_by_prod_manager' => $user->id,
                    'decline_comment_prod_manager' => $comment,
                ]);
                break;

            case RoleType::SENIOR_PRODUCTION_MANAGER:
                $this->update([
                    'declined_at_senior_prod_manager' => now(),
                    'declined_by_senior_prod_manager' => $user->id,
                    'decline_comment_senior_prod_manager' => $comment,
                ]);
                break;

            case RoleType::STOCK_MANAGER:
                $this->update([
                    'declined_at' => now(),
                    'declined_by' => $user->id,
                    'decline_comment' => $comment,
                ]);
                break;
        }
    }

    public function getDeclineDetails(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $aboveRole = $this->getNextRole($user->role);
        if (!$aboveRole) {
            return [];
        }

        return [
            'above' => match ($aboveRole) {
                RoleType::PRODUCTION_MANAGER => $this->declined_at_prod_manager ? [
                    'comment' => $this->decline_comment_prod_manager,
                    'by' => $this->declinedByProdManager->name,
                    'at' => $this->declined_at_prod_manager->format('d M Y H:i'),
                ] : null,
                RoleType::SENIOR_PRODUCTION_MANAGER => $this->declined_at_senior_prod_manager ? [
                    'comment' => $this->decline_comment_senior_prod_manager,
                    'by' => $this->declinedByProdSeniorManager->name,
                    'at' => $this->declined_at_senior_prod_manager->format('d M Y H:i'),
                ]: null,
                RoleType::STOCK_MANAGER => $this->declined_at ? [
                    'comment' => $this->decline_comment,
                    'by' => $this->declinedBy->name,
                    'at' => $this->declined_at->format('d M Y H:i'),
                ] : null
            },

            'own' => match ($user->role) {
                RoleType::PRODUCTION_MANAGER => $this->declined_at_prod_manager ? [
                    'comment' => $this->decline_comment_prod_manager,
                    'at' => $this->declined_at_prod_manager->format('d M Y H:i'),
                ] : null,
                RoleType::SENIOR_PRODUCTION_MANAGER => $this->declined_at_senior_prod_manager ? [
                    'comment' => $this->decline_comment_senior_prod_manager,
                    'at' => $this->declined_at_senior_prod_manager->format('d M Y H:i'),
                ]: null,
                RoleType::STOCK_MANAGER => $this->declined_at ? [
                    'comment' => $this->decline_comment,
                    'at' => $this->declined_at->format('d M Y H:i'),
                ]: null,
                default => null,
            }
        ];
    }

}
