<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: int implements HasColor, HasLabel
{
    case Pending = 1;
    case Processing = 2;
    case Completed = 3;
    case Approved = 4;
    case Cancelled = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Approved => 'Approved',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending                   => 'gray',
            self::Processing                => 'info',
            self::Completed, self::Approved => 'success',
            self::Cancelled                 => 'danger',
        };
    }
}
