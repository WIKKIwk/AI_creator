<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProdOrderStepProductStatus: int implements HasLabel, HasIcon, HasColor
{
    case InProgress = 0;
    case Completed = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Completed => 'Completed'
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::Completed => 'success'
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::InProgress => 'heroicon-o-clock',
            self::Completed => 'heroicon-o-check-circle'
        };
    }
}
