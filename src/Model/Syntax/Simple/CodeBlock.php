<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Symbol;
use App\Model\Syntax\Expression;

final class CodeBlock implements SimpleSyntax, Expression
{
    public function __construct(
        public readonly array $expressions,
        /** @var BlockReturn|null $return this is also present inside {@see self::$expressions} */
        public readonly ?BlockReturn $return,
        public readonly Symbol $closingBrace,
    ) {
    }
}
