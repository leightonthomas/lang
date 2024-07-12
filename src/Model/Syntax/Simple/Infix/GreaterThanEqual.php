<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Infix;

use App\Model\Compiler\CustomBytecode\Opcode;

readonly class GreaterThanEqual extends BinaryInfix
{
    public static function getOpcode(): Opcode
    {
        return Opcode::GREATER_THAN_EQ;
    }
}
