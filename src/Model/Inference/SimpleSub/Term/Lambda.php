<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

readonly class Lambda implements Term
{
    public function __construct(
        public string $name,
        public Term $rhs,
    ) {
    }

    public function __toString(): string
    {
        return "𝜆$this->name. $this->rhs";
    }
}
