<?php

declare(strict_types=1);

namespace App\Model\Inference;

use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable;
use ArrayAccess;
use JsonSerializable;
use TypeError;

use function array_key_exists;
use function array_map;
use function get_class;
use function is_string;

/**
 * Maps {@see Variable}s to {@see Monotype}s.
 */
final class Substitution implements ArrayAccess, JsonSerializable
{
    public function __construct(
        /** @var array<string, Monotype> $values */
        private array $values = [],
    ) {
    }

    public function combine(Substitution $other): self
    {
        /** @var array<string, Monotype> $result */
        $result = [];
        foreach ($this->values as $key => $value) {
            $result[$key] = $value;
        }

        // loop through S_2 ($other) and apply S_1 ($this) to the value, emulating S1(S2(x)) for combined substitution
        foreach ($other->values as $key => $value) {
            $result[$key] = $this->apply($value);
        }

        return new Substitution($result);
    }

    /**
     * @template T of Monotype|Polytype|Context
     *
     * @return T
     */
    public function apply(Monotype|Polytype|Context $to): Monotype|Polytype|Context
    {
        return match (get_class($to)) {
            Context::class => $to->copy()->map(fn(Polytype $p) => $this->apply($p)),
            Variable::class => $this[$to] ?? $to,
            Quantifier::class => new Quantifier($to->quantified, $this->apply($to->body)),
            Application::class => new Application(
                $to->constructor,
                array_map(fn (Monotype $arg) => $this->apply($arg), $to->arguments),
            ),
        };
    }

    public function offsetExists(mixed $offset): bool
    {
        if ($offset instanceof Variable) {
            $offset = $offset->name;
        }

        return array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): Monotype
    {
        if ($offset instanceof Variable) {
            $offset = $offset->name;
        }

        return $this->values[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (! ($value instanceof Monotype)) {
            throw new TypeError("Attempted to use a non-Monotype for a substitution value");
        }

        if ($offset instanceof Variable) {
            $offset = $offset->name;
        }

        if (! is_string($offset)) {
            throw new TypeError("Attempted to use a non-Variable for a substitution key");
        }

        $this->values[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if ($offset instanceof Variable) {
            $offset = $offset->name;
        }

        if (! is_string($offset)) {
            throw new TypeError("Attempted to use a non-Variable for a substitution key");
        }

        unset($this->values[$offset]);
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
