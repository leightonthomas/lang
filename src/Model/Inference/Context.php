<?php

declare(strict_types=1);

namespace App\Model\Inference;

use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable;
use ArrayAccess;
use JsonSerializable;
use RuntimeException;

use function array_diff;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_values;
use function is_string;

/**
 * Maps {@see Variable}s to {@see Polytype}s.
 */
final class Context implements ArrayAccess, JsonSerializable
{
    public function __construct(
        /** @var array<string, Polytype> $values */
        private array $values = [],
    ) {
    }

    /**
     * Apply $fn to each variable's polytype in the context.
     *
     * @param callable(Polytype):Polytype $fn
     */
    public function map(callable $fn): Context
    {
        return new Context(array_map($fn, $this->values));
    }

    public function copy(): Context
    {
        return new Context($this->values);
    }

    public function with(Variable|string $variable, Polytype $value): self
    {
        $this[$variable] = $value;

        return $this;
    }

    public function generalise(Monotype $type): Polytype
    {
        $typeVars = $type->getFreeVariables();
        $thisVars = $this->getFreeVariables();

        $quantifiers = array_values(array_diff($typeVars, $thisVars));

        /** @var Polytype $polytype */
        $polytype = $type;
        foreach ($quantifiers as $quantifier) {
            $polytype = new Quantifier($quantifier, $polytype);
        }

        return $polytype;
    }

    public function offsetExists(mixed $offset): bool
    {
        if ($offset instanceof Variable) {
            $offset = $offset->name;
        }

        return array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): Polytype
    {
        return $this->values[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (! ($value instanceof Polytype)) {
            throw new RuntimeException("Attempted to use a non-Polytype for a context value");
        }

        if ($offset instanceof Variable) {
            $offset = $offset->name;
        }

        if (! is_string($offset)) {
            throw new RuntimeException("Attempted to use a non-Variable for a context key");
        }

        $this->values[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[$offset]);
    }

    /**
     * @return list<string> the free variable **names**
     */
    public function getFreeVariables(): array
    {
        /** @var list<string> $output */
        $output = [];

        foreach ($this->values as $polytype) {
            $output = array_merge($output, $polytype->getFreeVariables());
        }

        return $output;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'type' => 'context',
            'values' => $this->values,
        ];
    }
}
