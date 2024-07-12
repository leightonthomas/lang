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
use App\Model\Syntax\Precedence;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Boolean;
use App\Model\Syntax\Simple\CodeBlock;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use App\Model\Syntax\Simple\Definition\VariableDefinition;
use App\Model\Syntax\Simple\IfStatement;
use App\Model\Syntax\Simple\Infix\Addition;
use App\Model\Syntax\Simple\Infix\FunctionCall;
use App\Model\Syntax\Simple\Infix\GreaterThan;
use App\Model\Syntax\Simple\Infix\GreaterThanEqual;
use App\Model\Syntax\Simple\Infix\LessThan;
use App\Model\Syntax\Simple\Infix\LessThanEqual;
use App\Model\Syntax\Simple\Infix\Subtraction;
use App\Model\Syntax\Simple\IntegerLiteral as IntegerLiteralExpr;
use App\Model\Syntax\Simple\Prefix\Group;
use App\Model\Syntax\Simple\Prefix\Minus;
use App\Model\Syntax\Simple\Prefix\Not;
use App\Model\Syntax\Simple\StringLiteral as StringLiteralExpr;
use App\Model\Syntax\Simple\TypeAssignment;
use App\Model\Syntax\Simple\Variable;
use App\Model\Syntax\SubExpression;

use function array_key_exists;
use function preg_match;
use function sprintf;

final class Parser
{
    private const int MAX_EXPRESSION_DEPTH = 1_000;
    private const string IDENTIFIER_REGEX = '/^[a-z][a-zA-Z]{0,49}$/';

    private ParsedOutput $output;
    /** @var Queue<Token> */
    private Queue $tokens;

    public function __construct(
        /** @param array<string, bool> $reservedIdentifiers */
        private readonly array $reservedIdentifiers = [],
    ) {
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
        // peek this rather than consume it, so we can keep the other parser methods consistent & contained
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

        if (
            (preg_match(self::IDENTIFIER_REGEX, $name->identifier) !== 1)
            || array_key_exists($name->identifier, $this->reservedIdentifiers)
        ) {
            throw new ParseFailure("Invalid function name identifier '$name->identifier'", $name);
        }

        $openParen = $this->tokens->pop();
        if (! Symbol::tokenIs($openParen, Symbol::PAREN_OPEN)) {
            throw ParseFailure::unexpectedToken(
                sprintf("expected symbol %s", Symbol::PAREN_OPEN->value),
                $openParen,
            );
        }

        /** @var list<array{name: Identifier type: TypeAssignment}> $arguments */
        $arguments = [];

        $maybeCloseParen = $this->tokens->peek();
        if (! Symbol::tokenIs($maybeCloseParen, Symbol::PAREN_CLOSE)) {
            do {
                $arguments[] = $this->parseArgument();

                $maybeCommaOrParenClose = $this->tokens->peek();
                if (Symbol::tokenIs($maybeCommaOrParenClose, Symbol::COMMA)) {
                    $this->tokens->pop();

                    continue;
                }

                if (Symbol::tokenIs($maybeCommaOrParenClose, Symbol::PAREN_CLOSE)) {
                    $this->tokens->pop();

                    break;
                }

                throw ParseFailure::unexpectedToken(
                    'expected another argument or end of function arguments',
                    $maybeCloseParen,
                );
            } while (true);
        } else {
            $this->tokens->pop();
        }

        $codeBlock = $this->parseExpressionBlock(currentExpressionDepth: 0);
        $function = new FunctionDefinition($keyword, $type, $name, $codeBlock, $arguments);

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

        while (true) {
            $next = $this->tokens->peek();

            if (KeywordModel::tokenIs($next, KeywordModel::RETURN)) {
                // pop the return, parse the actual expression
                $this->tokens->pop();

                $maybeEnd = $this->tokens->peek();
                if ($maybeEnd instanceof EndOfStatement) {
                    // pop the end-of-statement
                    $this->tokens->pop();

                    $expressions[] = new BlockReturn(null);
                } else {
                    $expressions[] = new BlockReturn($this->parseExpression($currentExpressionDepth + 1));
                }

                // anything after a return is unreachable
                break;
            }

            if (KeywordModel::tokenIs($next, KeywordModel::IF)) {
                $this->tokens->pop();

                $openParen = $this->tokens->pop();
                if (! Symbol::tokenIs($openParen, Symbol::PAREN_OPEN)) {
                    throw ParseFailure::unexpectedToken(
                        sprintf("expected symbol %s", Symbol::PAREN_OPEN->value),
                        $openParen,
                    );
                }

                $condition = $this->parseSubExpression($currentExpressionDepth + 1, Precedence::DEFAULT);

                $closeParen = $this->tokens->pop();
                if (! Symbol::tokenIs($closeParen, Symbol::PAREN_CLOSE)) {
                    throw ParseFailure::unexpectedToken(
                        sprintf("expected symbol %s", Symbol::PAREN_CLOSE->value),
                        $closeParen,
                    );
                }

                $expressions[] = new IfStatement($condition, $this->parseExpressionBlock($currentExpressionDepth + 1));

                continue;
            }

            if ($next instanceof Comment) {
                // ignore it
                $this->tokens->pop();

                continue;
            }

            if (KeywordModel::tokenIs($next, KeywordModel::LET)) {
                // pop the let, parse the actual expression
                $this->tokens->pop();

                $identifier = $this->tokens->pop();
                if (! ($identifier instanceof Identifier)) {
                    throw ParseFailure::unexpectedToken("expected variable identifier", $identifier);
                }

                if (
                    (preg_match(self::IDENTIFIER_REGEX, $identifier->identifier) !== 1)
                    || array_key_exists($identifier->identifier, $this->reservedIdentifiers)
                ) {
                    throw new ParseFailure("Invalid variable name identifier '$identifier->identifier'", $identifier);
                }

                $equal = $this->tokens->pop();
                if (! Symbol::tokenIs($equal, Symbol::EQUAL)) {
                    throw ParseFailure::unexpectedToken("expected variable identifier", $identifier);
                }

                $expressions[] = new VariableDefinition(
                    $identifier,
                    $this->parseExpression($currentExpressionDepth + 1),
                );

                continue;
            }

            if (Symbol::tokenIs($next, Symbol::BRACE_CLOSE)) {
                // they're trying to close the block
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

        return new CodeBlock($expressions, $braceClose);
    }

    /**
     * @throws ParseFailure
     */
    private function parseExpression(int $currentExpressionDepth): SubExpression|CodeBlock
    {
        $next = $this->tokens->peek();
        if ($currentExpressionDepth > self::MAX_EXPRESSION_DEPTH) {
            throw new ParseFailure(
                sprintf("Maximum expression depth of %d reached", self::MAX_EXPRESSION_DEPTH),
                $next,
            );
        }

        if ($next === null) {
            throw ParseFailure::unexpectedToken('expected an expression', $next);
        }

        if (Symbol::tokenIs($next, Symbol::BRACE_OPEN)) {
            $expression = $this->parseExpressionBlock($currentExpressionDepth + 1);
        } else {
            $expression = $this->parseSubExpression($currentExpressionDepth + 1, Precedence::DEFAULT);
        }

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

        // parse prefix, which acts as our LHS for infix operators
        if ($next instanceof SymbolToken) {
            $leftHandSide = match ($next->symbol) {
                Symbol::MINUS => new Minus($this->parseSubExpression($nextDepth, Precedence::PREFIX)),
                Symbol::EXCLAMATION => new Not($this->parseSubExpression($nextDepth, Precedence::PREFIX)),
                Symbol::PAREN_OPEN => new Group($this->parseSubExpression($nextDepth, Precedence::DEFAULT)),
                default => throw ParseFailure::unexpectedToken('expected prefix symbol', $next),
            };

            if ($leftHandSide instanceof Group) {
                $closingParenthesis = $this->tokens->pop();
                if (! Symbol::tokenIs($closingParenthesis, Symbol::PAREN_CLOSE)) {
                    throw ParseFailure::unexpectedToken('expected a closing parenthesis', $next);
                }
            }
        } else {
            $leftHandSide = match (true) {
                ($next instanceof StringLiteral) => new StringLiteralExpr($next),
                ($next instanceof IntegerLiteral) => new IntegerLiteralExpr($next),
                ($next instanceof Identifier) => new Variable($next),
                (KeywordModel::tokenIs($next, KeywordModel::TRUE)) => new Boolean(true, $next),
                (KeywordModel::tokenIs($next, KeywordModel::FALSE)) => new Boolean(false, $next),
                default => throw ParseFailure::unexpectedToken('expected a sub-expression', $next),
            };
        }

        // parse infix operators by precedence recursively
        while ($precedence->value < Precedence::getInfixPrecedence($this->tokens->peek())->value) {
            $infixToken = $this->tokens->pop();
            if (! ($infixToken instanceof SymbolToken)) {
                break;
            }

            $infixPrecedence = Precedence::getInfixPrecedence($infixToken);

            if (Symbol::tokenIs($infixToken, Symbol::PAREN_OPEN)) {
                /** @var list<SubExpression> $arguments */
                $arguments = [];

                $maybeCloseParen = $this->tokens->peek();
                if (! Symbol::tokenIs($maybeCloseParen, Symbol::PAREN_CLOSE)) {
                    do {
                        $arguments[] = $this->parseSubExpression($nextDepth, Precedence::DEFAULT);

                        $maybeCommaOrParenClose = $this->tokens->peek();
                        if (Symbol::tokenIs($maybeCommaOrParenClose, Symbol::COMMA)) {
                            $this->tokens->pop();

                            continue;
                        }

                        if (Symbol::tokenIs($maybeCommaOrParenClose, Symbol::PAREN_CLOSE)) {
                            $this->tokens->pop();

                            break;
                        }

                        throw ParseFailure::unexpectedToken(
                            'expected another argument or end of function call',
                            $infixToken,
                        );
                    } while (true);
                } else {
                    // get rid of the closing parenthesis
                    $this->tokens->pop();
                }

                $leftHandSide = new FunctionCall($leftHandSide, $arguments);
            } elseif (Symbol::tokenIs($infixToken, Symbol::ANGLE_OPEN)) {
                $subsequentToken = $this->tokens->peek();
                if (Symbol::tokenIs($subsequentToken, Symbol::EQUAL)) {
                    $this->tokens->pop();

                    $leftHandSide = new LessThanEqual($leftHandSide, $this->parseSubExpression($nextDepth, $infixPrecedence));
                } else {
                    $leftHandSide = new LessThan($leftHandSide, $this->parseSubExpression($nextDepth, $infixPrecedence));
                }
            } elseif (Symbol::tokenIs($infixToken, Symbol::ANGLE_CLOSE)) {
                $subsequentToken = $this->tokens->peek();
                if (Symbol::tokenIs($subsequentToken, Symbol::EQUAL)) {
                    $this->tokens->pop();

                    $leftHandSide = new GreaterThanEqual($leftHandSide, $this->parseSubExpression($nextDepth, $infixPrecedence));
                } else {
                    $leftHandSide = new GreaterThan($leftHandSide, $this->parseSubExpression($nextDepth, $infixPrecedence));
                }
            } else {
                $leftHandSide = match ($infixToken->symbol) {
                    Symbol::PLUS => new Addition($leftHandSide, $this->parseSubExpression($nextDepth, $infixPrecedence)),
                    Symbol::MINUS => new Subtraction($leftHandSide, $this->parseSubExpression($nextDepth, $infixPrecedence)),
                    default => throw ParseFailure::unexpectedToken('expected a valid infix symbol', $infixToken),
                };
            }
        }

        return $leftHandSide;
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

    /**
     * @return array{name: Identifier, type: TypeAssignment}
     *
     * @throws ParseFailure
     */
    private function parseArgument(): array
    {
        $type = $this->parseTypeAssignment();

        $name = $this->tokens->pop();
        if (! ($name instanceof Identifier)) {
            throw ParseFailure::unexpectedToken('expected argument name', $name);
        }

        return ['name' => $name, 'type' => $type];
    }
}
