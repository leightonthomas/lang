<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode;

enum Opcode : int
{
    case RET = 0; // RET
    case CALL = 1; // CALL "foo"
    case PUSH_INT = 2; // PUSH_INT 4
    case LET = 3; // LET "foo" (assigns current stack item to name)
    case ECHO = 4; // ECHO
    case LOAD = 5; // LOAD "foo"
    case END = 6; // END
    case SUB = 7; // SUB
    case ADD = 8; // ADD
    case NEG = 9; // NEGATE
    case PUSH_STRING = 10; // PUSH_STRING
    case PUSH_UNIT = 11; // PUSH_UNIT
}
