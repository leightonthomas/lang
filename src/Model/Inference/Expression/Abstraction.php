<?php

declare(strict_types=1);

namespace App\Model\Inference\Expression;

/**
 * x -> e
 */
readonly class Abstraction implements Expression
{
    public function __construct(
        public string $argument,
        public Expression $expression,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'abstraction',
            'argument' => $this->argument,
            'expression' => $this->expression,
        ];
    }
}
