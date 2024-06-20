<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Prefix;

use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\SubExpression;

abstract class Prefix implements SimpleSyntax, SubExpression
{
    public function __construct(
        public readonly SubExpression $operand,
    ) {
    }
}
