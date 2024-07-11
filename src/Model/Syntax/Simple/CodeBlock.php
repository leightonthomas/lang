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

    public function getFirstReturnStatement(): ?BlockReturn
    {
        foreach ($this->expressions as $expression) {
            if ($expression instanceof IfStatement) {
                $ifThenReturn = $expression->then->getFirstReturnStatement();
                if ($ifThenReturn !== null) {
                    return $ifThenReturn;
                }

                continue;
            }

            if (! ($expression instanceof BlockReturn)) {
                continue;
            }

            return $expression;
        }

        return null;
    }
}
