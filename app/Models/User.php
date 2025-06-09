<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Models\Scopes\OwnOrganizationScope;
use Exception;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $auth_code
 * @property RoleType $role
 * @property Carbon|null $email_verified_at
 * @property mixed $password
 * @property mixed $chat_id
 * @property int $organization_id
 * @property int $warehouse_id
 * @property int $work_station_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Organization $organization
 * @property Warehouse $warehouse
 * @property WorkStation $workStation
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country_id',
        'status',
        'role',
        'chat_id',
        'organization_id',
        'warehouse_id',
        'work_station_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => RoleType::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->generateAuthCode();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workStation(): BelongsTo
    {
        return $this->belongsTo(WorkStation::class);
    }

    public function scopeOwnOrganization(Builder $query): Builder
    {
        return $query->withGlobalScope('ownOrganization', new OwnOrganizationScope());
    }

    public function scopeExceptMe(Builder $query): Builder
    {
        return $query->whereNot('id', auth()->user()->id);
    }

    public function generateAuthCode(): void
    {
        $this->auth_code = Str::random(6);
    }

    /**
     * @throws Exception
     */
    public static function getFromChatId(int $chatId): self
    {
        $user = self::findByChatId($chatId);
        if (!$user) {
            throw new Exception("User not found by chat_id: $chatId");
        }
        return $user;
    }

    public static function findByChatId(int $chatId): ?self
    {
        /** @var User $user */
        $user = self::query()->where('chat_id', $chatId)->first();
        return $user;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->role == RoleType::WORK_STATION_WORKER) {
            return false;
        }

        if ($this->organization_id) {
            return $this->organization->isActive();
        }

        return true;
    }
}
