<?php

declare(strict_types=1);

namespace App\Model\Inference\Expression;

readonly class Variable implements Expression
{
    public function __construct(
        public string $name,
    ) {
    }

    public function jsonSerialize(): array
    {
        return ['type' => 'variable', 'name' => $this->name];
    }
}
