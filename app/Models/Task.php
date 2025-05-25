<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Enums\TaskAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $from_user_id
 * @property array $to_user_ids
 * @property array $to_user_roles
 * @property int $related_id
 * @property string $related_type
 * @property string $action
 * @property string $comment
 *
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $fromUser
 * @property User $toUser
 */
class Task extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'action' => TaskAction::class,
        'to_user_ids' => 'array',
        'to_user_roles' => 'array',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
