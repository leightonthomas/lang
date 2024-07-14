<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode;

enum JumpType : int
{
    case RELATIVE_BYTES = 0;
    case MARKER = 1;
}
