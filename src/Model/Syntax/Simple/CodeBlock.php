<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Symbol;
use App\Model\Syntax\Expression;

final class CodeBlock implements SimpleSyntax, Expression
{
    public function __construct(
        /** @var list<Expression> $expressions */
        public readonly array $expressions,
        public readonly Symbol $closingBrace,
    ) {
    }
}
