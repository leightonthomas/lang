<?php

declare(strict_types=1);

namespace App\Model\Syntax;

use App\Lexer\Token\Symbol as SymbolToken;
use App\Lexer\Token\Token;
use App\Model\Symbol;

enum Precedence : int
{
    case DEFAULT = 0;
    case COMPARISON = 1;
    case SUM = 2;
    case PRODUCT = 3;
    case PREFIX = 4;
    case CALL = 5;

    public static function getInfixPrecedence(?Token $token): self
    {
        return match (true) {
            ($token instanceof SymbolToken) => match ($token->symbol) {
                Symbol::PLUS, Symbol::MINUS => Precedence::SUM,
                Symbol::FORWARD_SLASH, Symbol::ASTERISK => Precedence::PRODUCT,
                Symbol::ANGLE_CLOSE, Symbol::ANGLE_OPEN, Symbol::EQUAL => Precedence::COMPARISON,
                Symbol::PAREN_OPEN => Precedence::CALL,
                default => Precedence::DEFAULT,
            },
            default => Precedence::DEFAULT,
        };
    }
}
