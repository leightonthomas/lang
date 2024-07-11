<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode;

enum Opcode : int
{
    case RET = 0;
    case CALL = 1; // CALL "foo"
    case PUSH_INT = 2; // PUSH_INT 4
    case LET = 3; // LET "foo" (assigns current stack item to name)
    case ECHO = 4;
    case LOAD = 5; // LOAD "foo"
    case END = 6;
    case SUB = 7;
    case ADD = 8;
    case NEGATE_INT = 9;
    case PUSH_STRING = 10;
    case PUSH_UNIT = 11;
    case PUSH_BOOL = 12;
    /**
     * JUMP 5
     * Expected stack: [
     *     Int|Bool(Value)
     *     Int(JumpFlag) ; controls how we interpret previous item in stack, whether we jump
     * ]
     */
    case JUMP = 13;
    case NEGATE_BOOL = 14;
    case START_FRAME = 15;
}
