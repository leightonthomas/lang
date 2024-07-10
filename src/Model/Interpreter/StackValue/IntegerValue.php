<?php

declare(strict_types=1);

namespace App\Model\Interpreter\StackValue;

readonly final class IntegerValue implements StackValue
{
    public function __construct(
        public int $value,
    ) {
    }
}
