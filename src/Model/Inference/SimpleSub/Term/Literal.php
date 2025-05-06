<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

// literal of any type; differs from the paper which treats this as only ever an integer
readonly class Literal implements Term
{
    public function __construct(
        public string $type,
    ) {
    }

    public function __toString(): string
    {
        return "lit($this->type)";
    }
}
