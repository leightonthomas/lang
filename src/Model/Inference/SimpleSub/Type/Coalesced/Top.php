<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type\Coalesced;

readonly class Top implements Type
{
    public function __toString(): string
    {
        return "⊤";
    }
}
