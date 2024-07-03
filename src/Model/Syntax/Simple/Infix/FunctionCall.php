<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Infix;

use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\SubExpression;

final readonly class FunctionCall implements SimpleSyntax, SubExpression
{
    public function __construct(
        public SubExpression $on,
        /** @var list<SubExpression> $arguments */
        public array $arguments,
    ) {
    }
}
