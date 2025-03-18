<?php

namespace App\Models;

enum StockProcessType: int
{
    case Entry = 1;
    case Exit = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Entry => 'Entry',
            self::Exit => 'Exit',
        };
    }
}
