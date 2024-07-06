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

    // temporary special built-in to handle quantifiers for now
    case ANY = '_any';
}
