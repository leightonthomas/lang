<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Model\Syntax\Expression;
use App\Model\Syntax\SubExpression;

readonly class IfStatement implements SimpleSyntax, Expression
{
    public function __construct(
        public SubExpression $condition,
        public CodeBlock $then,
    ) {
    }

    public function containsTopLevelReturnInAllPaths(): bool
    {
        $hasThenType = $this->then->getFirstReturnStatement() !== null;

        return $hasThenType;
    }
}
