<?php

namespace App\Enums;

enum DefaultStatus: int
{
    case Pending = 0;
    case Approved = 1;
    case Declined = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }
}
