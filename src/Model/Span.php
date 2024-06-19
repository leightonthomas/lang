<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;

/**
 * A span of characters in a 0-based input stream.
 */
readonly final class Span
{
    public function __construct(
        /** The first character (inclusive) */
        public int $start,
        /** The last character (inclusive) */
        public int $end,
    ) {
        if ($this->start > $this->end) {
            throw new InvalidArgumentException("Invalid Span; \$start must be <= \$end");
        }
    }
}
