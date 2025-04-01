<?php

namespace App\Enums;

enum Currency: string
{
    case UZS = 'UZS';
    case USD = 'USD';
    case EUR = 'EUR';
    case RUB = 'RUB';
    case KZT = 'KZT';

    public function getLabel(): string
    {
        return match ($this) {
            self::UZS => 'so\'m',
            self::USD => '$',
            self::EUR => '€',
            self::RUB => '₽',
            self::KZT => '₸',
        };
    }
}
