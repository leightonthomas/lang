<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type\Coalesced;

readonly class Funktion implements Type
{
    public function __construct(
        public Type $lhs,
        public Type $rhs,
    ) {
    }

    public function __toString(): string
    {
        return "$this->lhs -> $this->rhs";
    }
}
