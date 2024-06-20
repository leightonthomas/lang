<?php

declare(strict_types=1);

namespace App\Parser;

use App\Lexer\Token\Comment;
use App\Lexer\Token\EndOfStatement;
use App\Lexer\Token\Identifier;
use App\Lexer\Token\IntegerLiteral;
use App\Lexer\Token\Keyword;
use App\Lexer\Token\StringLiteral;
use App\Lexer\Token\Symbol as SymbolToken;
use App\Lexer\Token\Token;
use App\Model\DataStructure\Queue;
use App\Model\Exception\Parser\ParseFailure;
use App\Model\Keyword as KeywordModel;
use App\Model\Symbol;
use App\Model\Syntax\Expression;
use App\Model\Syntax\Precedence;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Boolean;
use App\Model\Syntax\Simple\CodeBlock;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use App\Model\Syntax\Simple\IntegerLiteral as IntegerLiteralExpr;
use App\Model\Syntax\Simple\Prefix\Group;
use App\Model\Syntax\Simple\Prefix\Minus;
use App\Model\Syntax\Simple\Prefix\Not;
use App\Model\Syntax\Simple\StringLiteral as StringLiteralExpr;
use App\Model\Syntax\Simple\TypeAssignment;
use App\Model\Syntax\Simple\Variable;
use App\Model\Syntax\SubExpression;

use function sprintf;

final class Parser
{
    private const int MAX_EXPRESSION_DEPTH = 100;

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

        // reset the state we had for this parse, there's no need for us to keep it around
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

        $codeBlock = $this->parseExpressionBlock(currentExpressionDepth: 0);
        $function = new FunctionDefinition($keyword, $type, $name, $codeBlock);

        $this->output->addFunction($function);

        return $function;
    }

    /**
     * @throws ParseFailure
     */
    private function parseExpressionBlock(int $currentExpressionDepth): CodeBlock
    {
        if ($currentExpressionDepth > self::MAX_EXPRESSION_DEPTH) {
            throw new ParseFailure(
                sprintf("Maximum expression depth of %d reached", self::MAX_EXPRESSION_DEPTH),
                $this->tokens->peek(),
            );
        }

        $braceOpen = $this->tokens->pop();
        if (! Symbol::tokenIs($braceOpen, Symbol::BRACE_OPEN)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::BRACE_OPEN->value),
                $braceOpen,
            );
        }

        $expressions = [];
        /** @var BlockReturn|null $returnExpression */
        $returnExpression = null;

        while (true) {
            $next = $this->tokens->peek();
            if (Symbol::tokenIs($next, Symbol::BRACE_CLOSE)) {
                // they're trying to close the block
                break;
            }

            if (KeywordModel::tokenIs($next, KeywordModel::RETURN)) {
                // pop the return, parse the actual expression
                $this->tokens->pop();

                $returnExpression = new BlockReturn($this->parseExpression($currentExpressionDepth + 1));
                $expressions[] = $returnExpression;

                // anything after a return is unreachable
                break;
            }

            $expressions[] = $this->parseExpression($currentExpressionDepth + 1);
        }

        $braceClose = $this->tokens->pop();
        if (! Symbol::tokenIs($braceClose, Symbol::BRACE_CLOSE)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::BRACE_CLOSE->value),
                $braceClose,
            );
        }

        return new CodeBlock($expressions, $returnExpression, $braceClose);
    }

    /**
     * @throws ParseFailure
     */
    private function parseExpression(int $currentExpressionDepth): Expression
    {
        $next = $this->tokens->peek();
        if ($currentExpressionDepth > self::MAX_EXPRESSION_DEPTH) {
            throw new ParseFailure(
                sprintf("Maximum expression depth of %d reached", self::MAX_EXPRESSION_DEPTH),
                $next,
            );
        }

        $expression = match (true) {
            // another block, which should be allowed
            Symbol::tokenIs($next, Symbol::BRACE_OPEN) => $this->parseExpressionBlock($currentExpressionDepth + 1),
            ($next === null) => throw ParseFailure::unexpectedToken('expected an expression', $next),
            default => $this->parseSubExpression($currentExpressionDepth + 1, Precedence::DEFAULT),
        };

        $endOfStatement = $this->tokens->pop();
        if (! ($endOfStatement instanceof EndOfStatement)) {
            throw ParseFailure::unexpectedToken('expected end of statement', $endOfStatement);
        }

        return $expression;
    }

    /**
     * @throws ParseFailure
     */
    private function parseSubExpression(int $currentExpressionDepth, Precedence $precedence): SubExpression
    {
        $next = $this->tokens->pop();
        if ($currentExpressionDepth > self::MAX_EXPRESSION_DEPTH) {
            throw new ParseFailure(
                sprintf("Maximum expression depth of %d reached", self::MAX_EXPRESSION_DEPTH),
                $next,
            );
        }

        $nextDepth = $currentExpressionDepth + 1;

        // parse prefix
        if ($next instanceof SymbolToken) {
            $subExpression = match ($next->symbol) {
                Symbol::MINUS => new Minus($this->parseSubExpression($nextDepth, Precedence::PREFIX)),
                Symbol::EXCLAMATION => new Not($this->parseSubExpression($nextDepth, Precedence::PREFIX)),
                Symbol::PAREN_OPEN => new Group($this->parseSubExpression($nextDepth, Precedence::DEFAULT)),
                default => throw ParseFailure::unexpectedToken('expected prefix symbol', $next),
            };

            if ($subExpression instanceof Group) {
                $closingParenthesis = $this->tokens->pop();
                if (! Symbol::tokenIs($closingParenthesis, Symbol::PAREN_CLOSE)) {
                    throw ParseFailure::unexpectedToken('expected a closing parenthesis', $next);
                }
            }

            return $subExpression;
        }

        return match (true) {
            ($next instanceof StringLiteral) => new StringLiteralExpr($next),
            ($next instanceof IntegerLiteral) => new IntegerLiteralExpr($next),
            ($next instanceof Identifier) => new Variable($next),
            (KeywordModel::tokenIs($next, KeywordModel::TRUE)) => new Boolean(true, $next),
            (KeywordModel::tokenIs($next, KeywordModel::FALSE)) => new Boolean(false, $next),
            default => throw ParseFailure::unexpectedToken('expected a sub-expression', $next),
        };
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
}
