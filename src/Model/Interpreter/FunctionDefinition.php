<?php

declare(strict_types=1);

namespace App\Model\Interpreter;

final readonly class FunctionDefinition
{
    public function __construct(
        public string $name,
        public int $offset,
        /** @var list<string> */
        public array $arguments,
        public int $lengthOfContentInBytes,
    ) {
    }
}
