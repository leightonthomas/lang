<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Model\Syntax\SubExpression;

readonly class ArrayDeclaration implements SimpleSyntax, SubExpression
{
    /** @param list<SubExpression> $entries */
    public function __construct(
        public array $entries,
    ) {
    }
}
