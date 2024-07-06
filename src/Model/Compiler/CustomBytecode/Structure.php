<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode;

/**
 * Essentially these are opcodes for defining the structure of the program so that the interpreter can store
 * things like functions/constants/etc
 */
enum Structure : int
{
    case FN = 0;
    case END = 1;
}
