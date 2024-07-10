<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode;

enum JumpMode : int
{
    case IF_FALSE = 0; // jump if previous value on stack is false
}
