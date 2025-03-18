<?php

namespace App\Enums;

enum OrganizationType: int
{
    use BaseEnum;

    case AGENT = 1;
    case SUPPLIER = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::AGENT => 'Agent',
            self::SUPPLIER => 'Supplier',
        };
    }
}
