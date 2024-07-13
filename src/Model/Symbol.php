<?php

declare(strict_types=1);

namespace App\Model;

use App\Lexer\Token\Symbol as SymbolToken;
use App\Lexer\Token\Token;

enum Symbol : string
{
    case EQUAL = '=';
    case PAREN_OPEN = '(';
    case PAREN_CLOSE = ')';
    case BRACE_OPEN = '{';
    case BRACE_CLOSE = '}';
    case BRACKET_OPEN = '[';
    case BRACKET_CLOSE = ']';
    case ANGLE_OPEN = '<';
    case ANGLE_CLOSE = '>';
    case COMMA = ',';
    case PERIOD = '.';
    case COLON = ':';
    case PLUS = '+';
    case MINUS = '-';
    case FORWARD_SLASH = '/';
    case EXCLAMATION = '!';
    case QUESTION = '?';
    case ASTERISK = '*';
    case CARET = '^';
    case AMPERSAND = '&';

    public function isPrefix(): bool
    {
        return match ($this) {
            self::MINUS, self::EXCLAMATION, self::PAREN_OPEN => true,
            default => false,
        };
    }

    public static function tokenIs(?Token $token, Symbol $symbol): bool
    {
        return ($token instanceof SymbolToken) && ($token->symbol === $symbol);
    }
}
