<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProdOrderProductType: int implements HasLabel, HasColor
{
    case Required = 1;
    case Expected = 2;
    case Produced = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Required => 'Required',
            self::Expected => 'Expected',
            self::Produced => 'Produced',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Required => 'warning',
            self::Expected => 'info',
            self::Produced => 'success',
        };
    }
}
