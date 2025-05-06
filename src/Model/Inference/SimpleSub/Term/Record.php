<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

use function rtrim;

readonly class Record implements Term
{
    /** @param array<string, Term> $fields */
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
