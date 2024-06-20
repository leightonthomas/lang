<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\IntegerLiteral as IntegerToken;
use App\Model\Syntax\SubExpression;

readonly class IntegerLiteral implements SimpleSyntax, SubExpression
{
    public function __construct(
        public IntegerToken $base,
    ) {
    }
}
