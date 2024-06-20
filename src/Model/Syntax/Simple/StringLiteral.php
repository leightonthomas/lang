<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\StringLiteral as StringToken;
use App\Model\Syntax\SubExpression;

readonly class StringLiteral implements SimpleSyntax, SubExpression
{
    public function __construct(
        public StringToken $base,
    ) {
    }
}
