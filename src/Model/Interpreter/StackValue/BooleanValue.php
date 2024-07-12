<?php

declare(strict_types=1);

namespace App\Model\Interpreter\StackValue;

readonly final class BooleanValue implements StackValue
{
    public function __construct(
        public bool $value,
    ) {
    }

    public function equals(StackValue $other): bool
    {
        return ($other instanceof BooleanValue) && ($this->value === $other->value);
    }
}
