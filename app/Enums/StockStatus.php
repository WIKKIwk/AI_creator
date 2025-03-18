<?php

namespace App\Enums;

enum StockStatus: int
{
    use BaseEnum;

    case NotReady = 0;
    case Ready = 1;
    case Approved = 2;
    case Rejected = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::NotReady => 'Not Ready',
            self::Ready => 'Ready',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
