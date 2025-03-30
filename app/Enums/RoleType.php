<?php

namespace App\Enums;

enum RoleType: int
{
    case ADMIN = 1;
    case PLANNING_MANAGER = 2;
    case PRODUCTION_MANAGER = 3;
    case ALLOCATION_MANAGER = 4;
    case STOCK_MANAGER = 5;
    case LEADER = 6;
    case WORK_STATION_MANAGER = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::PLANNING_MANAGER => 'Planning Manager',
            self::PRODUCTION_MANAGER => 'Production Manager',
            self::ALLOCATION_MANAGER => 'Allocation Manager',
            self::STOCK_MANAGER => 'Stock Manager',
            self::LEADER => 'Leader',
            self::WORK_STATION_MANAGER => 'Work Station Manager',
        };
    }
}
