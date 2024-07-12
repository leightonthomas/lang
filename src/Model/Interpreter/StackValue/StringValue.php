<?php

declare(strict_types=1);

namespace App\Model\Interpreter\StackValue;

readonly final class StringValue implements StackValue
{
    public function __construct(
        public string $value,
    ) {
    }

    public function equals(StackValue $other): bool
    {
        return ($other instanceof StringValue) && ($this->value === $other->value);
    }
}
