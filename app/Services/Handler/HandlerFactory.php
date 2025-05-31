<?php

namespace App\Services\Handler;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Handler\Interface\HandlerInterface;
use App\Services\Handler\ProductionManager\ProductionManagerHandler;

class HandlerFactory
{
    public static function make(User $user): HandlerInterface
    {
        return match ($user->role) {
            RoleType::PRODUCTION_MANAGER => app(ProductionManagerHandler::class),
            RoleType::WORK_STATION_WORKER => app(WorkStationWorkerHandler::class),
            default => app(BaseHandler::class),
        };
    }
}
