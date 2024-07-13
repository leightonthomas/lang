<?php

declare(strict_types=1);

namespace App\Model\DataStructure;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use OutOfRangeException;
use RuntimeException;
use SplQueue;
use Traversable;

use function max;

/**
 * Thin wrapper around {@see SplQueue} to make it a bit easier to work with, and the intent clearer
 *
 * @template T
 */
final readonly class Queue implements ArrayAccess, IteratorAggregate, Countable
{
    private SplQueue $inner;

    public function __construct()
    {
        $this->inner = new SplQueue();
    }

    /**
     * @param non-negative-int $depth how far into the queue to peek; 0 for current item, 1 for the previous, etc.
     *
     * @return T|null
     */
    public function peek(int $depth = 0): mixed
    {
        try {
            return $this->inner->offsetGet(max(0, $depth));
        } catch (RuntimeException|OutOfRangeException) {
            return null;
        }
    }

    /**
     * @param T $value
     */
    public function push(mixed $value): void
    {
        $this->inner->enqueue($value);
    }

    /**
     * @return T|null
     */
    public function pop(): mixed
    {
        try {
            return $this->inner->dequeue();
        } catch (RuntimeException) {
            return null;
        }
    }

    public function isEmpty(): bool
    {
        return $this->inner->isEmpty();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->inner->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->inner->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->inner->offsetSet($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->inner->offsetUnset($offset);
    }

    public function getIterator(): Traversable
    {
        return $this->inner;
    }

    public function count(): int
    {
        return $this->inner->count();
    }
}
