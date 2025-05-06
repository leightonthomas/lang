<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

readonly class Application implements Term
{
    public function __construct(
        public Term $lhs,
        public Term $rhs,
    ) {
    }

    public function __toString(): string
    {
        return "$this->lhs $this->rhs";
    }
}
