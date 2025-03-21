<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductType: int implements HasColor, HasLabel
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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RawMaterial => 'gray',
            self::SemiFinishedProduct => 'yellow',
            self::ReadyProduct => 'success',
        };
    }
}
