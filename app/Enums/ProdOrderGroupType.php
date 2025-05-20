<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ProdOrderGroupType: int implements HasLabel, HasColor, HasIcon
{
    case ByOrder = 1;
    case ByCatalog = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::ByOrder => 'By order',
            self::ByCatalog => 'By catalog',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ByOrder => 'success',
            self::ByCatalog => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ByOrder => 'heroicon-o-document-text',
            self::ByCatalog => 'heroicon-o-collection',
        };
    }
}
