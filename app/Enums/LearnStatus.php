<?php

namespace App\Enums;

enum LearnStatus:int
{
    use BaseEnum;

    case Hard = 0;
    case Normal = 1;
    case Learned = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Hard    => 'Hard',
            self::Normal  => 'Normal',
            self::Learned => 'Learned',
        };
    }


}
