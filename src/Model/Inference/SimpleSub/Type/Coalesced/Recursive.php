<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type\Coalesced;

readonly class Recursive implements Type
{
    public function __construct(
        public string $name,
        public Type $body,
    ) {
    }

    public function __toString(): string
    {
        return "𝜇$this->name. $this->body";
    }
}
