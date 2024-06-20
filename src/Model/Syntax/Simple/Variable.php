<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Identifier;
use App\Model\Syntax\SubExpression;

readonly class Variable implements SimpleSyntax, SubExpression
{
    public function __construct(
        public Identifier $base,
    ) {
    }
}
