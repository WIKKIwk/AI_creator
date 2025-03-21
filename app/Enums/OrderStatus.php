<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum OrderStatus: int implements HasColor
{
    case Pending = 1;
    case Processing = 2;
    case Completed = 3;
    case Cancelled = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
