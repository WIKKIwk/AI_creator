<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoleType: int implements HasLabel, HasColor, HasIcon
{
    case ADMIN = 1;
    case PLANNING_MANAGER = 2;
    case PRODUCTION_MANAGER = 3;
    case ALLOCATION_MANAGER = 4;
    case STOCK_MANAGER = 5;
    case LEADER = 6;
    case WORK_STATION_WORKER = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::PLANNING_MANAGER => 'Planning Manager',
            self::PRODUCTION_MANAGER => 'Production Manager',
            self::ALLOCATION_MANAGER => 'Allocation Manager',
            self::STOCK_MANAGER => 'Stock Manager',
            self::LEADER => 'Leader',
            self::WORK_STATION_WORKER => 'Work Station worker',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ADMIN => 'primary',
            self::PLANNING_MANAGER => 'success',
            self::PRODUCTION_MANAGER => 'warning',
            self::ALLOCATION_MANAGER => 'info',
            self::STOCK_MANAGER => 'danger',
            self::LEADER => 'secondary',
            self::WORK_STATION_WORKER => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ADMIN => 'heroicon-o-user-circle',
            self::PLANNING_MANAGER => 'heroicon-o-chart-bar',
            self::PRODUCTION_MANAGER => 'heroicon-o-cog',
            self::ALLOCATION_MANAGER => 'heroicon-o-cog',
            self::STOCK_MANAGER => 'heroicon-o-cog',
            self::LEADER => 'heroicon-o-shield-check',
            self::WORK_STATION_WORKER => 'heroicon-o-cog',
        };
    }
}
