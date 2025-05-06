<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type;

readonly class Variable implements SimpleType
{
    public function __construct(
        public VariableState $state,
    ) {
    }

    public function __toString(): string
    {
        return "{$this->state->uniqueName}";
    }
}
