<?php

namespace App\Services;

use App\Enums\RoleType;

class UserService
{
    public static function isSuperAdmin(): bool
    {
        return self::isAdmin() && is_null(auth()->user()->organization_id);
    }

    public static function isAdmin(): bool
    {
        return auth()->user()->role === RoleType::ADMIN;
    }
}
