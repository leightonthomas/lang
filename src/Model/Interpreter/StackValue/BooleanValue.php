<?php

declare(strict_types=1);

namespace App\Model\Interpreter\StackValue;

readonly final class BooleanValue implements StackValue
{
    public function __construct(
        public bool $value,
    ) {
    }
}
