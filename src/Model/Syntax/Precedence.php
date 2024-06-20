<?php

declare(strict_types=1);

namespace App\Model\Syntax;

enum Precedence : int
{
    case DEFAULT = 0;
    case SUM = 1;
    case PRODUCT = 2;
    case PREFIX = 3;
    case CALL = 4;
}
