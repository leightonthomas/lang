<?php

declare(strict_types=1);

namespace App\Lexer;

final class Position
{
    public function __construct(
        public int $index,
        public int $column,
        public int $row,
    ) {
    }
}
