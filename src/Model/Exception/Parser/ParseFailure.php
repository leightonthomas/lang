<?php

declare(strict_types=1);

namespace App\Model\Exception\Parser;

use App\Lexer\Token\Token;
use Exception;

final class ParseFailure extends Exception
{
    public static function unexpectedEndOfInput(string $because): self
    {
        return new self("Unexpected end of input - $because", null);
    }

    public static function unexpectedToken(string $expected, ?Token $currentToken): self
    {
        if ($currentToken === null) {
            return self::unexpectedEndOfInput($expected);
        }

        return new self("Unexpected token - $expected", $currentToken);
    }

    public function __construct(
        string $message,
        public readonly ?Token $lastToken,
    ) {
        parent::__construct($message);
    }
}
