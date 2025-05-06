<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type;

readonly class Primitive implements SimpleType
{
    public function __construct(
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
