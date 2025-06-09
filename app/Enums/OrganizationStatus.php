<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum OrganizationStatus: int implements HasLabel, HasColor, HasIcon
{
    case Active = 1;
    case Blocked = 2;
    case Archived = 3;
    case Verified = 10;

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Blocked => 'Blocked',
            self::Archived => 'Archived',
            self::Verified => 'Verified',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Blocked => 'danger',
            self::Archived => 'secondary',
            self::Verified => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Blocked => 'heroicon-o-x-circle',
            self::Archived => 'heroicon-o-archive',
            self::Verified => 'heroicon-o-shield-check',
        };
    }
}
