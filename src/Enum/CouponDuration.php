<?php

namespace Foundry\Enum;

enum CouponDuration: string
{
    case FOREVER = 'forever';
    case ONCE = 'once';
    case REPEATING = 'repeating';
}
