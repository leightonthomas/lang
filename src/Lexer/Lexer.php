<?php

declare(strict_types=1);

namespace App\Lexer;

use App\Lexer\Token\Comment;
use App\Lexer\Token\EndOfStatement;
use App\Lexer\Token\Identifier;
use App\Lexer\Token\IntegerLiteral;
use App\Lexer\Token\Keyword;
use App\Lexer\Token\StringLiteral;
use App\Lexer\Token\Symbol;
use App\Lexer\Token\Token;
use App\Model\DataStructure\Queue;
use App\Model\Exception\Lexer\LexerFailure;
use App\Model\Keyword as KeywordModel;
use App\Model\Reader\PushbackReader;
use App\Model\Span;
use App\Model\Symbol as SymbolModel;
use InvalidArgumentException;

use function ctype_alpha;
use function ctype_digit;
use function ctype_space;

final class Lexer
{
    private Position $position;
    private string $lexeme;
    /** @var Queue<Token> */
    private Queue $tokens;
    private PushbackReader $reader;

    /**
     * @param resource $fileResource
     *
     * @return Queue<Token>
     *
     * @throws LexerFailure
     * @throws InvalidArgumentException if $fileResource is not a resource.
     */
    public function lex($fileResource): Queue
    {
        $this->reset($fileResource);

        // empty file handling, etc
        $firstByte = $this->reader->read();
        if ($firstByte === null) {
            return $this->tokens;
        }

        // put it back to normalise things, makes looping a bit easier
        $this->reader->unread($firstByte);

        while (true) {
            $this->lexeme = "";
            $this->skipWhitespace();

            $currentChar = $this->advance();
            if ($currentChar === null) {
                break;
            }

            $currentIndex = $this->position->index;

            match (true) {
                ($currentChar === ';') => $this->tokens[] = new EndOfStatement(new Span($currentIndex, $currentIndex)),
                (($currentChar === '/') && ($this->reader->peek() === '/')) => $this->comment($currentIndex),
                ($currentChar === '"') => $this->stringLiteral($currentIndex),
                ctype_digit($currentChar) => $this->integerLiteral($currentChar, $currentIndex),
                ctype_alpha($currentChar) => $this->identifierOrKeyword($currentChar, $currentIndex),
                (SymbolModel::tryFrom($currentChar) !== null) => $this->symbol($currentChar, $currentIndex),
                default => throw new LexerFailure("Unrecognised character: $currentChar"),
            };
        }

        $this->reader->close();

        // get rid of the tokens from in here, no need for us to keep a copy
        $tokens = $this->tokens;
        $this->tokens = new Queue();

        return $tokens;
    }

    private function skipWhitespace(): void
    {
        while (true) {
            $next = $this->reader->peek();

            // let somewhere else handle this
            if ($next === null) {
                return;
            }

            if (! ctype_space($next)) {
                break;
            }

            $this->advance();
        }
    }

    private function advance(): ?string
    {
        $next = $this->reader->read();
        if ($next !== null) {
            if ($next === "\n") {
                $this->position->column = 0;
                $this->position->row += 1;
            } else {
                $this->position->column += 1;
            }

            $this->position->index += 1;
        }

        return $next;
    }

    private function comment(int $startIndex): void
    {
        // skip the forward slash that we peeked
        $this->advance();

        // we only support single-line comments technically
        while (true) {
            $next = $this->advance();
            if ($next === "\n") {
                // up to but NOT including the newline itself
                $this->tokens[] = new Comment(new Span($startIndex, $this->position->index - 1));

                break;
            } elseif ($next === null) {
                $this->tokens[] = new Comment(new Span($startIndex, $this->position->index));

                break;
            }
        }
    }

    /**
     * @throws LexerFailure
     */
    private function stringLiteral(int $startIndex): void
    {
        while (true) {
            $next = $this->advance();
            if ($next === null) {
                throw new LexerFailure('Unexpected end of input; expected end of string or string contents.');
            }

            // handle escaped characters
            if ($next === '\\') {
                $escaped = $this->advance();
                if ($escaped === null) {
                    throw new LexerFailure('Unexpected end of input; expected an escaped character.');
                }

                $this->lexeme .= $next;
                $this->lexeme .= $escaped;

                continue;
            } elseif ($next === '"') {
                $this->tokens[] = new StringLiteral(new Span($startIndex, $this->position->index), $this->lexeme);

                // they terminated the string with an unescaped quote
                break;
            }

            $this->lexeme .= $next;
        }
    }

    private function integerLiteral(string $firstCharacter, int $startIndex): void
    {
        $this->lexeme .= $firstCharacter;

        while (true) {
            // peek so we don't consume the next non-integer character just in-case
            $next = $this->reader->peek();
            if (! ctype_digit($next)) {
                break;
            }

            $this->lexeme .= $next;
            $this->advance();
        }

        $this->tokens[] = new IntegerLiteral(
            new Span($startIndex, $this->position->index),
            $this->lexeme,
        );
    }

    private function identifierOrKeyword(mixed $firstCharacter, int $startIndex): void
    {
        $this->lexeme .= $firstCharacter;

        while (true) {
            // peek so we don't consume the next non-related character
            $next = $this->reader->peek();
            if (! ctype_alpha($next)) {
                break;
            }

            $this->lexeme .= $next;
            $this->advance();
        }

        // check for keyword
        $span = new Span($startIndex, $this->position->index);

        $maybeKeyword = KeywordModel::tryFrom($this->lexeme);
        if ($maybeKeyword === null) {
            $this->tokens[] = new Identifier($span, $this->lexeme);

            return;
        }

        $this->tokens[] = new Keyword($span, $maybeKeyword);
    }

    private function symbol(string $character, int $startIndex): void
    {
        // we'll have already validated it if this was called, so no need to "tryFrom" instead
        $this->tokens[] = new Symbol(new Span($startIndex, $this->position->index), SymbolModel::from($character));
    }

    /**
     * @param resource $fileResource
     *
     * @throws InvalidArgumentException if $fileResource is not a resource.
     */
    private function reset($fileResource): void
    {
        $this->position = new Position(-1, 0, 0);
        $this->lexeme = "";
        $this->tokens = new Queue();
        $this->reader = new PushbackReader($fileResource);
    }
}
