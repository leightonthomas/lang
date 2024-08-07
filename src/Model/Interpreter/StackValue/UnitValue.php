<?php

declare(strict_types=1);

namespace App\Model\Interpreter\StackValue;

readonly final class UnitValue implements StackValue
{
    public function equals(StackValue $other): bool
    {
        return true;
    }
}
