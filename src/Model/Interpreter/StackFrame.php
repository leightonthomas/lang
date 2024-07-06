<?php

declare(strict_types=1);

namespace App\Model\Interpreter;

use function array_key_last;
use function array_pop;

final class StackFrame
{
    /** @var list<int> */
    private array $stack;
    /** @var array<string, int> the name -> value */
    private array $namedValues;

    public function __construct(
        public readonly string $functionName,
        public readonly int $returnPointer,
    ) {
        $this->stack = [$returnPointer];
        $this->namedValues = [];
    }

    public function setNamedValue(string $name, int $value): void
    {
        $this->namedValues[$name] = $value;
    }

    public function getNamedValue(string $name): int
    {
        return $this->namedValues[$name];
    }

    public function get(): int
    {
        return $this->stack[array_key_last($this->stack)];
    }

    public function pop(): int
    {
        return array_pop($this->stack);
    }

    public function push(int $value): void
    {
        $this->stack[] = $value;
    }
}
