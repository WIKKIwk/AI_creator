<?php

namespace App\Services\Handler;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Handler\Interface\HandlerInterface;
use App\Services\Handler\Ai\AiHandler;
use App\Services\Handler\Codex\CodexHandler;
use App\Services\Handler\ProductionManager\ProductionManagerHandler;
use App\Services\Handler\StockManager\StockManagerHandler;
use App\Services\Handler\SupplyManager\SupplyManagerHandler;
use App\Services\Handler\WorkerHandler\WorkerHandler;

class HandlerFactory
{
    public static function make(User $user): HandlerInterface
    {
        return match ($user->role) {
            RoleType::AI_ASSISTANT => app(AiHandler::class),
            RoleType::CODEX => app(CodexHandler::class),
            // Planning and Allocation managers currently share features with production/supply flows
            RoleType::PLANNING_MANAGER => app(ProductionManagerHandler::class),
            RoleType::SENIOR_PRODUCTION_MANAGER,
            RoleType::PRODUCTION_MANAGER => app(ProductionManagerHandler::class),

            RoleType::ALLOCATION_MANAGER,
            RoleType::SUPPLY_MANAGER,
            RoleType::SENIOR_SUPPLY_MANAGER => app(SupplyManagerHandler::class),

            RoleType::STOCK_MANAGER,
            RoleType::SENIOR_STOCK_MANAGER => app(StockManagerHandler::class),

            RoleType::WORK_STATION_WORKER => app(WorkerHandler::class),

            default => app(BaseHandler::class),
        };
    }
}
