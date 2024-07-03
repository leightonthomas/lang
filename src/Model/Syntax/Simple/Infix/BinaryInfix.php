<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Infix;

use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\SubExpression;

abstract readonly class BinaryInfix implements SimpleSyntax, SubExpression
{
    public function __construct(
        public SubExpression $left,
        public SubExpression $right,
    ) {
    }
}
