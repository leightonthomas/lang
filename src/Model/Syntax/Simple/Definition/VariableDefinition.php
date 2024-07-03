<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Definition;

use App\Lexer\Token\Identifier;
use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\SubExpression;

readonly class VariableDefinition implements SimpleSyntax
{
    public function __construct(
        public Identifier $name,
        public SubExpression $value,
    ) {
    }
}
