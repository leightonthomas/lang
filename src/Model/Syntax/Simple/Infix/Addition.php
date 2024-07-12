<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Infix;

use App\Model\Compiler\CustomBytecode\Opcode;

readonly class Addition extends BinaryInfix
{
    public static function getOpcode(): Opcode
    {
        return Opcode::ADD;
    }
}
