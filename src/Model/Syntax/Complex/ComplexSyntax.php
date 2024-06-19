<?php

declare(strict_types=1);

namespace App\Model\Syntax\Complex;

use App\Model\Syntax\Simple\SimpleSyntax;

/**
 * Represents the syntactic sugar of the language that can be simplified down to {@see SimpleSyntax}.
 */
interface ComplexSyntax
{
    /**
     * @return list<SimpleSyntax>
     */
    public function simplify(): array;
}
