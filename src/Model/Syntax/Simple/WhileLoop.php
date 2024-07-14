<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Model\Syntax\Expression;
use App\Model\Syntax\SubExpression;

readonly class WhileLoop implements SimpleSyntax, Expression
{
    public function __construct(
        public SubExpression $condition,
        public CodeBlock $block,
    ) {
    }
}
