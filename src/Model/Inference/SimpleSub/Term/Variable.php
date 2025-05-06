<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

readonly class Variable implements Term
{
    public function __construct(
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return "$this->name";
    }
}
