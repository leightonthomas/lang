<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple;

use App\Lexer\Token\Identifier;
use Stringable;

use function count;
use function rtrim;

readonly class TypeAssignment implements SimpleSyntax, Stringable
{
    public function __construct(
        /** @var Identifier $base The base type */
        public Identifier $base,
        /** @var list<TypeAssignment> $arguments */
        public array $arguments = [],
    ) {
    }

    public function __toString(): string
    {
        $string = $this->base->identifier;

        if (count($this->arguments) <= 0) {
            return $string;
        }

        $string .= "<";

        foreach ($this->arguments as $argument) {
            $string .= $argument->__toString() . ", ";
        }

        $string = rtrim($string, ", ");

        return $string . ">";
    }
}
