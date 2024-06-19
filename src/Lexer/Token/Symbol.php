<?php

declare(strict_types=1);

namespace App\Lexer\Token;

use App\Model\Span;
use App\Model\Symbol as SymbolModel;

readonly final class Symbol extends Token
{
    public function __construct(
        Span $span,
        public SymbolModel $symbol,
    ) {
        parent::__construct($span);
    }
}
