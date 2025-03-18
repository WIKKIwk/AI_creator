<?php

namespace App\Enums;

enum ProductType: int
{
    use BaseEnum;

    case RawMaterial = 1;
    case SemiFinishedProduct = 2;
    case ReadyProduct = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw Material',
            self::SemiFinishedProduct => 'Semi Finished Product',
            self::ReadyProduct => 'Ready Product',
        };
    }
}
