<?php

declare(strict_types=1);

namespace App\Model\Interpreter;

use App\Model\Interpreter\StackValue\IntegerValue;
use App\Model\Interpreter\StackValue\StackValue;

use function array_key_exists;
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
        public readonly ?int $returnPointer,
        /** @param StackFrame|null $previous the previous stack frame, used to return to it after we're done here */
        public readonly ?StackFrame $previous,
        /** @param StackFrame|null $parent the PARENT stack frame, used for named value access */
        public readonly ?StackFrame $parent,
    ) {
        $this->stack = [];
        if ($this->returnPointer !== null) {
            $this->push(new IntegerValue($returnPointer));
        }

        $this->namedValues = [];
    }

    public function setNamedValue(string $name, StackValue $value): void
    {
        if ($this->parent?->hasNamedValue($name)) {
            $this->parent->setNamedValue($name, $value);

            return;
        }

        $this->namedValues[$name] = $value;
    }

    public function hasNamedValue(string $name): bool
    {
        return array_key_exists($name, $this->namedValues);
    }

    public function getNamedValue(string $name): StackValue
    {
        return $this->namedValues[$name] ?? $this->parent?->getNamedValue($name);
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
