<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type;

readonly class Funktion implements SimpleType
{
    public function __construct(
        public SimpleType $lhs,
        public SimpleType $rhs,
    ) {
    }

    public function __toString(): string
    {
        return "fn($this->lhs, $this->rhs)";
    }
}
