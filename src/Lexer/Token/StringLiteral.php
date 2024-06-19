<?php

declare(strict_types=1);

namespace App\Lexer\Token;

use App\Model\Span;

readonly final class StringLiteral extends Token
{
    public function __construct(
        Span $span,
        public string $content,
    ) {
        parent::__construct($span);
    }
}
