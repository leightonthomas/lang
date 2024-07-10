<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Model\Syntax\Expression;
use App\Model\Syntax\SubExpression;

final class BlockReturn implements SimpleSyntax, Expression
{
    public function __construct(
        public readonly CodeBlock|SubExpression|null $expression,
    ) {
    }
}
