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
    case INT_GREATER_THAN = '_greaterThan';
    case INT_GREATER_THAN_EQ = '_greaterThanEq';
    case INT_LESS_THAN = '_lessThan';
    case INT_LESS_THAN_EQ = '_lessThanEq';

    case BOOL_NEGATION = '_negateBool';

    case EQUALITY = '_equality';
    case REASSIGNMENT = '_reassign';

    case BOOL_CONDITION = '_condition';
}
