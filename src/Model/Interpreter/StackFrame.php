<?php

declare(strict_types=1);

namespace App\Model\Interpreter;

use App\Model\Interpreter\StackValue\IntegerValue;
use App\Model\Interpreter\StackValue\StackValue;

use function array_key_last;
use function array_pop;

final class StackFrame
{
    /** @var list<StackValue> */
    private array $stack;
    /** @var array<string, StackValue> */
    private array $namedValues;

    public function __construct(
        public readonly string $functionName,
        public readonly int $returnPointer,
    ) {
        $this->stack = [new IntegerValue($returnPointer)];
        $this->namedValues = [];
    }

    public function setNamedValue(string $name, StackValue $value): void
    {
        $this->namedValues[$name] = $value;
    }

    public function getNamedValue(string $name): StackValue
    {
        return $this->namedValues[$name];
    }

    public function get(): StackValue
    {
        return $this->stack[array_key_last($this->stack)];
    }

    public function pop(): StackValue
    {
        return array_pop($this->stack);
    }

    public function push(StackValue $value): void
    {
        $this->stack[] = $value;
    }
}
