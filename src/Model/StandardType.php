<?php

declare(strict_types=1);

namespace App\Model;

/**
 * The term "type" here refers to a Hindley-Milner type
 */
enum StandardType : string
{
    case STRING = 'string';
    case BOOL = 'bool';
    case INT = 'int';
    case UNIT = 'unit';

    case FUNCTION_APPLICATION = '_fn';

    case INT_ADDITION = '_intAddition';
    case INT_SUBTRACTION = '_intSubtraction';
    case INT_NEGATION = '_negateInt';

    case BOOL_NEGATION = '_negateBool';
}
