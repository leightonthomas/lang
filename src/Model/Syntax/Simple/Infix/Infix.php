<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Infix;

use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\SubExpression;

abstract class Infix implements SimpleSyntax, SubExpression
{
    public function __construct(
        public readonly SubExpression $left,
        public readonly SubExpression $right,
    ) {
    }
}
