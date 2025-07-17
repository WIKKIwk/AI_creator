<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: int implements HasLabel, HasColor
{
    use BaseEnum;

    case In = 1;
    case Out = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'Kirim',
            self::Out => 'Chiqim',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'danger',
        };
    }
}
