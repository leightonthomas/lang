<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Identifier;

readonly class TypeAssignment implements SimpleSyntax
{
    public function __construct(
        /** @var Identifier The base type */
        public Identifier $base,
    ) {
    }
}
