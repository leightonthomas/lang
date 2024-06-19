<?php

declare(strict_types=1);

namespace App\Parser;

use App\Lexer\Token\Comment;
use App\Lexer\Token\Identifier;
use App\Lexer\Token\Keyword;
use App\Lexer\Token\Token;
use App\Model\DataStructure\Queue;
use App\Model\Exception\Parser\ParseFailure;
use App\Model\Keyword as KeywordModel;
use App\Model\Symbol;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use App\Model\Syntax\Simple\TypeAssignment;

use function sprintf;

final class Parser
{
    private ParsedOutput $output;
    /** @var Queue<Token> */
    private Queue $tokens;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * @param Queue<Token> $tokens
     *
     * @throws ParseFailure
     */
    public function parse(Queue $tokens): ParsedOutput
    {
        // store this so we don't have to pass it around everywhere, we won't be testing them in isolation anyway
        $this->tokens = $tokens;
        unset($tokens);

        while (! $this->tokens->isEmpty()) {
            $this->parseTopLevel();
        }

        $result = $this->output;
        $this->reset();

        return $result;
    }

    /**
     * @throws ParseFailure
     */
    private function parseTopLevel(): void
    {
        // peek this rather than consume it so we can keep the other parser methods consistent & contained
        $next = $this->tokens->peek();

        match (true) {
            ($next instanceof Comment) => $this->tokens->pop(),
            ($next instanceof Keyword) => match ($next->keyword) {
                KeywordModel::FUNCTION => $this->parseFunction(),
                default => throw new ParseFailure('Invalid keyword provided for top-level declaration.', $next, $next),
            },
            default => throw new ParseFailure('Expected top-level declaration', $next, $next),
        };
    }

    /**
     * @throws ParseFailure
     */
    private function parseFunction(): FunctionDefinition
    {
        $keyword = $this->tokens->pop();
        if (! KeywordModel::tokenIs($keyword, KeywordModel::FUNCTION)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected keyword %s", KeywordModel::FUNCTION->value),
                $keyword,
            );
        }

        $type = $this->parseTypeAssignment();

        $name = $this->tokens->pop();
        if (! ($name instanceof Identifier)) {
            throw ParseFailure::unexpectedToken("expected function name identifier", $name);
        }

        $openParen = $this->tokens->pop();
        if (! Symbol::tokenIs($openParen, Symbol::PAREN_OPEN)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::PAREN_OPEN->value),
                $openParen,
            );
        }

        // TODO parse function arguments eventually (include parenthesis in there perhaps?

        $closeParen = $this->tokens->pop();
        if (! Symbol::tokenIs($closeParen, Symbol::PAREN_CLOSE)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::PAREN_CLOSE->value),
                $closeParen,
            );
        }

        $braceOpen = $this->tokens->pop();
        if (! Symbol::tokenIs($braceOpen, Symbol::BRACE_OPEN)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::BRACE_OPEN->value),
                $braceOpen,
            );
        }

        // TODO parse function body scope (maybe do scopes later?)

        $braceClose = $this->tokens->pop();
        if (! Symbol::tokenIs($braceClose, Symbol::BRACE_CLOSE)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::BRACE_CLOSE->value),
                $braceClose,
            );
        }

        $function = new FunctionDefinition($keyword, $type, $name, $braceClose);

        $this->output->addFunction($function);

        return $function;
    }

    /**
     * @throws ParseFailure
     */
    private function parseTypeAssignment(): TypeAssignment
    {
        $type = $this->tokens->pop();
        if (! ($type instanceof Identifier)) {
            throw ParseFailure::unexpectedToken('expected type assignment', $type);
        }

        return new TypeAssignment($type);
    }

    private function reset(): void
    {
        $this->output = new ParsedOutput();
        $this->tokens = new Queue();
    }

    private function skip(): void
    {
        $this->tokens->pop();
    }
}
