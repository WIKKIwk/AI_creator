<?php

namespace App\Enums;

enum DurationUnit: int
{
    use BaseEnum;

    case Year = 1;
    case Month = 2;
    case Week = 3;
    case Day = 4;
    case Hour = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::Year => 'Year',
            self::Month => 'Month',
            self::Week => 'Week',
            self::Day => 'Day',
            self::Hour => 'Hour',
        };
    }
}
