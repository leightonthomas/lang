<?php

declare(strict_types=1);

namespace App\Model;

enum Keyword : string
{
    case RETURN = 'return';
    case FUNCTION = 'fn';
    case LET = 'let';
    case TRUE = 'true';
    case FALSE = 'false';
}
