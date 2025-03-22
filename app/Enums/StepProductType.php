<?php

namespace App\Enums;

enum StepProductType: int
{
    case Required = 1;
    case Expected = 2;
    case Actual = 3;
}
