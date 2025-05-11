<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskAction: string implements HasLabel, HasColor, HasIcon
{
    case Check = 'check';
    case Confirm = 'confirm';
    case Approve = 'approve';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Check => 'success',
            self::Confirm => 'warning',
            self::Approve => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Check => 'heroicon-o-exclamation-circle',
            self::Confirm => 'heroicon-o-exclamation-circle',
            self::Approve => 'heroicon-o-exclamation-circle',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Check => 'Check',
            self::Confirm => 'Confirm',
            self::Approve => 'Approve',
        };
    }
}
