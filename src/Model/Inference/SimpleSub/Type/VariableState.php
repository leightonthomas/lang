<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type;

// This is explicitly not readonly as in the paper
class VariableState
{
    /**
     * @param list<SimpleType> $lowerBounds
     * @param list<SimpleType> $upperBounds
     */
    public function __construct(
        public readonly string $uniqueName,
        public array $lowerBounds,
        public array $upperBounds,
    ) {
    }
}
