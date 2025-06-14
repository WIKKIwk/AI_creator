<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartnerOrderType: int implements HasColor, HasLabel
{
    case OnlyOrder = 1;
    case Catalog = 2;
    case Both = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::OnlyOrder => 'Only Order',
            self::Catalog => 'Catalog',
            self::Both => 'Both',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OnlyOrder => 'success',
            self::Catalog => 'info',
            self::Both => 'warning',
        };
    }
}
