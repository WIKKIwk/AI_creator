<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SupplyOrderState: int implements HasLabel, HasColor, HasIcon
{
    case Created = 1;
    case InProgress = 2;
    case Delivered = 3;
    case Closed = 4;
    case Canceled = 5;
    case Rejected = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::InProgress => 'In Progress',
            self::Delivered => 'Delivered',
            self::Closed => 'Closed',
            self::Canceled => 'Canceled',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Created => 'primary',
            self::InProgress => 'warning',
            self::Delivered => 'success',
            self::Closed => 'secondary',
            self::Canceled, self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Created => 'heroicon-o-document-text',
            self::InProgress => 'heroicon-o-refresh',
            self::Delivered => 'heroicon-o-check-circle',
            self::Closed, self::Canceled, self::Rejected => 'heroicon-o-x-circle',
        };
    }
}
