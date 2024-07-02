<?php

declare(strict_types=1);

namespace App\Model\Inference\Type;

use function array_filter;
use function array_values;

final readonly class Quantifier implements Polytype
{
    public function __construct(
        public string $quantified,
        public Polytype $body,
    ) {
    }

    public function getFreeVariables(): array
    {
        return array_values(
            array_filter(
                $this->body->getFreeVariables(),
                fn (string $name) => $name !== $this->quantified,
            ),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'quantifier',
            'quantified' => $this->quantified,
            'body' => $this->body,
        ];
    }
}
