<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

readonly class Selector implements Term
{
    public function __construct(
        public Term $receiver,
        public string $field,
    ) {
    }

    public function __toString(): string
    {
        return "$this->receiver.$this->field";
    }
}
