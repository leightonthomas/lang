<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type\Coalesced;

use function rtrim;

readonly class Record implements Type
{
    /**
     * @param array<string, Type> $fields
     */
    public function __construct(
        public array $fields,
    ) {
    }

    public function __toString(): string
    {
        $string = "{ ";
        foreach ($this->fields as $name => $type) {
            $string .= "$name = $type; ";
        }

        $string = rtrim($string, "; ");

        return "$string }";
    }
}
