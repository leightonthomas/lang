<?php

declare(strict_types=1);

namespace App\Model\Inference\Expression;

/**
 * let x ($variable) = e1 ($value) in e2 ($in)
 */
readonly class Let implements Expression
{
    public function __construct(
        public string $variable,
        public Expression $value,
        public Expression $in,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'let',
            'variable' => $this->variable,
            'value' => $this->value,
            'in' => $this->in,
        ];
    }
}
