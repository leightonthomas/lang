<?php

declare(strict_types=1);

namespace App\Lexer\Token;

use App\Model\Span;

readonly final class IntegerLiteral extends Token
{
    public function __construct(
        Span $span,
        /** @var numeric-string $integer */
        public string $integer,
    ) {
        parent::__construct($span);
    }
}
