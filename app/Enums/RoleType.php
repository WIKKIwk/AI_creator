<?php

namespace App\Enums;

enum RoleType: int
{
    case ADMIN = 1;
    case PRODUCTION_MANAGER = 2;
    case ALLOCATION_MANAGER = 3;
    case STOCK_MANAGER = 4;
    case LEADER = 5;
    case WORK_STATION_MANAGER = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::PRODUCTION_MANAGER => 'Production Manager',
            self::ALLOCATION_MANAGER => 'Allocation Manager',
            self::STOCK_MANAGER => 'Stock Manager',
            self::LEADER => 'Leader',
            self::WORK_STATION_MANAGER => 'Work Station Manager',
        };
    }
}
