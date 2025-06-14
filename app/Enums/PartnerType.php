<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PartnerType: int implements HasLabel, HasColor, HasIcon
{
    use BaseEnum;

    case Agent = 1;
    case Supplier = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Agent => 'Agent',
            self::Supplier => 'Supplier',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Agent => 'primary',
            self::Supplier => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Agent => 'heroicon-o-user-group',
            self::Supplier => 'heroicon-o-truck',
        };
    }
}
