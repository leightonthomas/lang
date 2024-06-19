<?php

declare(strict_types=1);

namespace App\Lexer\Token;

use App\Model\Keyword as KeywordModel;
use App\Model\Span;

readonly final class Keyword extends Token
{
    public function __construct(
        Span $span,
        public KeywordModel $keyword,
    ) {
        parent::__construct($span);
    }
}
