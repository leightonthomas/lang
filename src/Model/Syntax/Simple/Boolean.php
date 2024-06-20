<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Keyword;
use App\Model\Syntax\SubExpression;

readonly class Boolean implements SimpleSyntax, SubExpression
{
    public function __construct(
        public bool $value,
        public Keyword $token,
    ) {
    }
}
