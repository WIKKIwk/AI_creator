<?php

namespace App\Services\Handler;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Handler\Interface\HandlerInterface;
use App\Services\Handler\ProductionManager\ProductionManagerHandler;
use App\Services\Handler\SupplyManager\SupplyManagerHandler;
use App\Services\Handler\WorkerHandler\WorkerHandler;

class HandlerFactory
{
    public static function make(User $user): HandlerInterface
    {
        return match ($user->role) {
            RoleType::PRODUCTION_MANAGER => app(ProductionManagerHandler::class),

            RoleType::SUPPLY_MANAGER, RoleType::SENIOR_SUPPLY_MANAGER => app(SupplyManagerHandler::class),

            RoleType::WORK_STATION_WORKER => app(WorkerHandler::class),

            default => app(BaseHandler::class),
        };
    }
}
