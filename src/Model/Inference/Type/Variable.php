<?php

declare(strict_types=1);

namespace App\Model\Inference\Type;

use App\Model\StandardType;

final readonly class Variable implements Monotype
{
    public string|StandardType $name;

    public function __construct(
        string|StandardType $name,
    ) {
        $this->name = ($name instanceof StandardType) ? $name->value : $name;
    }

    public function equals(Monotype $b): bool
    {
        if ( ! ($b instanceof Variable)) {
            return false;
        }

        return $this->name === $b->name;
    }

    public function getFreeVariables(): array
    {
        return [$this->name];
    }

    public function contains(Variable $variable): bool
    {
        return $this->equals($variable);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'variable',
            'variable' => $this->name,
        ];
    }
}
