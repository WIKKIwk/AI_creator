<?php

namespace App\Services\Handler;

use App\Enums\RoleType;
use App\Models\User;
use Exception;

class HandlerFactory
{
    /**
     * @throws Exception
     */
    public static function make(User $user): HandlerInterface
    {
        return match ($user->role) {
            RoleType::ADMIN => app(WarehouseManagerHandler::class),
//            'admin' => new AdminHandler(),
//            'order_manager' => new OrderManagerHandler(),
            default => throw new Exception("No handler found for role: " . $user->role->getLabel()),
        };
    }
}
