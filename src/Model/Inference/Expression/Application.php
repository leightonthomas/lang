<?php

declare(strict_types=1);

namespace App\Model\Inference\Expression;

/**
 * e1 e2
 */
readonly class Application implements Expression
{
    public function __construct(
        public Expression $left,
        public Expression $right,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'application',
            'left' => $this->left,
            'right' => $this->right,
        ];
    }
}
