<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type;

readonly class Record implements SimpleType
{
    /**
     * @param array<string, SimpleType> $fields
     */
    public function __construct(
        public array $fields,
    ) {
    }

    public function __toString(): string
    {
        return "record";
    }
}
