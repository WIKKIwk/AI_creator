<?php

namespace App\Models;

use App\Enums\RoleType;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property RoleType $role
 * @property Carbon|null $email_verified_at
 * @property mixed $password
 * @property mixed $chat_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
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
        'chat_id'
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

    /**
     * @throws Exception
     */
    public static function getFromChatId(int $chatId): self
    {
        /** @var User $user */
        $user = self::query()->where('chat_id', $chatId)->first();
        if (!$user) {
            throw new Exception("User not found by chat_id: $chatId");
        }
        return $user;
    }
}
