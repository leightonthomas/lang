<?php

declare(strict_types=1);

namespace App\Lexer\Token;

use App\Model\Span;

abstract readonly class Token
{
    public function __construct(
        public Span $span,
    ) {
    }
}
