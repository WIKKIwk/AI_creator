<?php

namespace App\Services\Handler;

use App\Enums\RoleType;
use App\Models\User;
use Exception;

class HandlerFactory
{
    public static function make(User $user): HandlerInterface
    {
        return match ($user->role) {
            RoleType::WORK_STATION_WORKER => app(WorkStationWorkerHandler::class),
            default => null,
        };
    }
}
