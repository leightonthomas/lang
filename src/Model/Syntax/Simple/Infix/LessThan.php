<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Infix;

use App\Model\Compiler\CustomBytecode\Opcode;

readonly class LessThan extends BinaryInfix
{
    public static function getOpcode(): Opcode
    {
        return Opcode::LESS_THAN;
    }
}
