<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Identifier;
use App\Model\Syntax\Expression;
use App\Model\Syntax\SubExpression;

readonly class VariableReassignment implements SimpleSyntax, Expression
{
    public function __construct(
        public Identifier $variable,
        public SubExpression|CodeBlock $newValue,
    ) {
    }
}
