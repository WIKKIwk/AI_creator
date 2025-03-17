<?php

namespace App\Enums;

trait BaseEnum
{
    public static function values(): array
    {
        return collect(self::cases())->map(fn ($case) => $case->value)->values()->toArray();
    }
}
