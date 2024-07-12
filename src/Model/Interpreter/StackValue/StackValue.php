<?php

declare(strict_types=1);

namespace App\Model\Interpreter\StackValue;

interface StackValue
{
    public function equals(StackValue $other): bool;
}
