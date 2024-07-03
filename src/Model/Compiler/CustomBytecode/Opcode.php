<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode;

enum Opcode : int
{
    case RETURN = 0; // RET
    case GOTO = 1; // GOTO "bar"
    case PUSH = 2; // PUSH 4
    case POP = 3; // POP
    case LET = 4; // LET "foo" (assigns current stack item to name)
    case ECHO = 5; // ECHO
    case LOAD = 6; // LOAD "foo"
    case END = 7; // END
}
