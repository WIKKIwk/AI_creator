<?php

namespace App\Enums;

enum MeasureUnit: int
{
    use BaseEnum;

    case PCS = 1;
    case KG = 2;
    case GRAM = 3;
    case METER = 4;
    case CENTIMETER = 5;
    case MILLIMETER = 6;
    case LITER = 7;
    case MILLILITER = 8;

    public function getLabel(): string
    {
        return match ($this) {
            self::PCS => 'PCS',
            self::KG => 'KG',
            self::GRAM => 'GRAM',
            self::METER => 'METER',
            self::CENTIMETER => 'CENTIMETER',
            self::MILLIMETER => 'MILLIMETER',
            self::LITER => 'LITER',
            self::MILLILITER => 'MILLILITER',
        };
    }
}
