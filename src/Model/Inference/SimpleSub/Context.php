<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub;

use App\Model\Inference\SimpleSub\Type\SimpleType;
use ArrayAccess;
use RuntimeException;
use Stringable;

use function array_key_exists;
use function array_merge;
use function is_string;
use function rtrim;

class Context implements ArrayAccess, Stringable
{
    public function __construct(
        /** @var array<string, SimpleType> $values */
        private array $values = [],
    ) {
    }

    public function with(string $variable, SimpleType $value): self
    {
        return new Context(array_merge($this->values, [$variable => $value]));
    }

    public function offsetExists(mixed $offset): bool
    {
        if (! is_string($offset)) {
            throw new RuntimeException("Attempted to use a non-string for a context key");
        }

        return array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): SimpleType
    {
        if (! is_string($offset)) {
            throw new RuntimeException("Attempted to use a non-string for a context key");
        }

        return $this->values[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (! ($value instanceof SimpleType)) {
            throw new RuntimeException("Attempted to use a non-SimpleType for a context value");
        }

        if (! is_string($offset)) {
            throw new RuntimeException("Attempted to use a non-string for a context key");
        }

        $this->values[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[$offset]);
    }

    public function __toString(): string
    {
        if (empty($this->values)) {
            return "empty";
        }

        $string = "Map(";

        foreach ($this->values as $key => $value) {
            $string .= "$key ↦→ $value, ";
        }

        $string = rtrim($string, ", ");

        return "$string)";
    }
}
