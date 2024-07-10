<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Model\Syntax\SubExpression;

readonly class IfStatement implements SimpleSyntax, SubExpression
{
    public function __construct(
        public SubExpression $condition,
        public CodeBlock $then,
    ) {
    }
}
