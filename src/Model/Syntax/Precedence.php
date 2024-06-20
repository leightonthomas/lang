<?php

declare(strict_types=1);

namespace App\Model\Syntax;

use App\Lexer\Token\Symbol as SymbolToken;
use App\Lexer\Token\Token;
use App\Model\Symbol;

enum Precedence : int
{
    case DEFAULT = 0;
    case SUM = 1;
    case PRODUCT = 2;
    case PREFIX = 3;
    case CALL = 4;

    public static function getInfixPrecedence(?Token $token): self
    {
        return match (true) {
            ($token instanceof SymbolToken) => match ($token->symbol) {
                Symbol::PLUS, Symbol::MINUS => Precedence::SUM,
                Symbol::FORWARD_SLASH, Symbol::ASTERISK => Precedence::PRODUCT,
                Symbol::PAREN_OPEN => Precedence::CALL,
                default => Precedence::DEFAULT,
            },
            default => Precedence::DEFAULT,
        };
    }
}
