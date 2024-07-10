<?php

declare(strict_types=1);

namespace App\Model;

use App\Lexer\Token\Keyword as KeywordToken;
use App\Lexer\Token\Token;

enum Keyword : string
{
    case RETURN = 'return';
    case FUNCTION = 'fn';
    case LET = 'let';
    case TRUE = 'true';
    case FALSE = 'false';
    case IF = 'if';

    public static function tokenIs(?Token $token, Keyword $keyword): bool
    {
        return ($token instanceof KeywordToken) && ($token->keyword === $keyword);
    }
}
