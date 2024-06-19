<?php

declare(strict_types=1);

namespace App\Model\Reader;

use InvalidArgumentException;

use function array_pop;
use function fclose;
use function feof;
use function fread;
use function is_resource;
use function rewind;

/**
 * Modelled after the PushbackReader from Java, allowing you to "put characters back" into the stream (but not really)
 * without having to seek the file etc. This is a bare-bones implementation with little to no safety features (e.g.
 * max pushed-back buffer size).
 *
 * It's assumed to be "ready" at construction, and is assumed to be single byte based.
 */
final class PushbackReader
{
    /** @var array<int, string> bytes that have been pushed back into the stream; first-in, last-out */
    private array $pushedBack = [];

    /**
     * @throws InvalidArgumentException if $handle is not a valid resource
     */
    public function __construct(
        /** @var resource */
        private $handle,
    ) {
        if (! is_resource($this->handle)) {
            throw new InvalidArgumentException("PushbackReader requires an open resource.");
        }

        rewind($this->handle);
    }

    /**
     * @return string|null the next character, or `null` if the end of the file was reached.
     */
    public function read(): ?string
    {
        $popped = array_pop($this->pushedBack);
        if ($popped !== null) {
            return $popped;
        }

        if (feof($this->handle)) {
            return null;
        }

        $result = fread($this->handle, 1);
        if (($result === false) || ($result === '')) {
            return null;
        }

        return $result;
    }

    public function peek(): ?string
    {
        $value = $this->read();
        if ($value !== null) {
            $this->unread($value);
        }

        return $value;
    }

    public function unread(string $byte): void
    {
        $this->pushedBack[] = $byte;
    }

    public function close(): void
    {
        fclose($this->handle);
    }
}
