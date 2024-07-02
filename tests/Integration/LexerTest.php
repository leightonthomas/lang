<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Lexer\Lexer;
use App\Lexer\Position;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function is_resource;
use function iterator_to_array;
use function sprintf;

#[CoversClass(Lexer::class)]
#[CoversClass(PushbackReader::class)]
#[CoversClass(Position::class)]
#[CoversClass(Token::class)]
#[CoversClass(Span::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(IntegerLiteral::class)]
#[CoversClass(Keyword::class)]
#[CoversClass(StringLiteral::class)]
#[CoversClass(Symbol::class)]
#[CoversClass(Queue::class)]
class LexerTest extends TestCase
{
    /**
     * @param list<Token> $expected
     */
    #[Test]
    #[DataProvider('lexerProvider')]
    public function itWillProduceTheCorrectOutputForAnInput(string $fixture, array $expected): void
    {
        $path = sprintf("%s/../Fixtures/Integration/Lexer/%s", __DIR__, $fixture);

        $resource = fopen($path, 'r');
        self::assertIsResource($resource, "Could not open fixture: $path");

        try {
            $lexer = new Lexer();
            $output = $lexer->lex($resource);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        self::assertEquals($expected, iterator_to_array($output));
    }

    /**
     * @return list<array{0: string, 1: list<Token>}>
     */
    public static function lexerProvider(): array
    {
        return [
            ['empty.txt', []],
            ['emptyLotsOfLines.txt', []],
            [
                'comment.txt',
                [
                    new Comment(new Span(0, 7)),
                    new Comment(new Span(9, 18)),
                ],
            ],
            [
                'endOfStatement.txt',
                [
                    new EndOfStatement(new Span(0, 0)),
                    new EndOfStatement(new Span(7, 7)),
                    new EndOfStatement(new Span(16, 16)),
                    new EndOfStatement(new Span(23, 23)),
                ],
            ],
            [
                'identifier.txt',
                [
                    new Identifier(new Span(0, 4), 'hello'),
                    new Identifier(new Span(15, 19), 'world'),
                    new Identifier(new Span(38, 41), 'some'),
                    new Identifier(new Span(43, 47), 'thing'),
                    new Identifier(new Span(49, 52), 'else'),
                ],
            ],
            [
                'integerLiteral.txt',
                [
                    new IntegerLiteral(new Span(0, 0), '1'),
                    new IntegerLiteral(new Span(6, 9), '2345'),
                    new IntegerLiteral(new Span(14, 24), '29287347109'),
                    new IntegerLiteral(new Span(30, 40), '01273849576'),
                    new IntegerLiteral(new Span(43, 53), '27261827840'),
                ],
            ],
            [
                'keyword.txt',
                [
                    new Keyword(new Span(1, 6), KeywordModel::RETURN),
                    new Keyword(new Span(9, 10), KeywordModel::FUNCTION),
                    new Keyword(new Span(12, 14), KeywordModel::LET),
                    new Keyword(new Span(17, 21), KeywordModel::FALSE),
                    new Keyword(new Span(23, 27), KeywordModel::FALSE),
                    new Keyword(new Span(33, 36), KeywordModel::TRUE),
                    new Keyword(new Span(38, 40), KeywordModel::LET),
                    new Keyword(new Span(43, 44), KeywordModel::FUNCTION),
                ],
            ],
            [
                'stringLiteral.txt',
                [
                    new StringLiteral(new Span(0, 1), ''),
                    new StringLiteral(new Span(7, 14), 'abc123'),
                    new StringLiteral(new Span(29, 45), 'def   45      6'),
                    new StringLiteral(new Span(54, 66), 'hello\nhere'),
                ],
            ],
            [
                'symbol.txt',
                [
                    new Symbol(new Span(1, 1), SymbolModel::EQUAL),
                    new Symbol(new Span(5, 5), SymbolModel::PAREN_OPEN),
                    new Symbol(new Span(7, 7), SymbolModel::PAREN_CLOSE),
                    new Symbol(new Span(12, 12), SymbolModel::BRACE_OPEN),
                    new Symbol(new Span(20, 20), SymbolModel::BRACE_CLOSE),
                    new Symbol(new Span(22, 22), SymbolModel::BRACKET_CLOSE),
                    new Symbol(new Span(23, 23), SymbolModel::BRACKET_OPEN),
                    new Symbol(new Span(25, 25), SymbolModel::ANGLE_OPEN),
                    new Symbol(new Span(26, 26), SymbolModel::ANGLE_CLOSE),
                    new Symbol(new Span(27, 27), SymbolModel::PERIOD),
                    new Symbol(new Span(31, 31), SymbolModel::COMMA),
                    new Symbol(new Span(33, 33), SymbolModel::COLON),
                    new Symbol(new Span(34, 34), SymbolModel::PLUS),
                    new Symbol(new Span(35, 35), SymbolModel::MINUS),
                    new Symbol(new Span(36, 36), SymbolModel::FORWARD_SLASH),
                    new Symbol(new Span(37, 37), SymbolModel::EXCLAMATION),
                    new Symbol(new Span(38, 38), SymbolModel::QUESTION),
                    new Symbol(new Span(50, 50), SymbolModel::AMPERSAND),
                    new Symbol(new Span(58, 58), SymbolModel::CARET),
                ],
            ],
            [
                'realExample.txt',
                [
                    new Comment(new Span(0, 15)),
                    new Keyword(new Span(17, 18), KeywordModel::FUNCTION),
                    new Identifier(new Span(20, 23), 'void'),
                    new Identifier(new Span(25, 28), 'main'),
                    new Symbol(new Span(29, 29), SymbolModel::PAREN_OPEN),
                    new Identifier(new Span(30, 32), 'int'),
                    new Identifier(new Span(34, 38), 'thing'),
                    new Symbol(new Span(39, 39), SymbolModel::PAREN_CLOSE),
                    new Symbol(new Span(41, 41), SymbolModel::BRACE_OPEN),
                    new Keyword(new Span(47, 52), KeywordModel::RETURN),
                    new Identifier(new Span(54, 58), 'thing'),
                    new Symbol(new Span(60, 60), SymbolModel::PLUS),
                    new IntegerLiteral(new Span(62, 62), '1'),
                    new EndOfStatement(new Span(63, 63)),
                    new Symbol(new Span(65, 65), SymbolModel::BRACE_CLOSE),
                ],
            ],
        ];
    }

    #[Test]
    public function itWillThrowIfStringLiteralFailsUnexpectedly(): void
    {
        $this->expectException(LexerFailure::class);
        $this->expectExceptionMessage('Unexpected end of input; expected end of string or string contents.');

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, '"hello');

        try {
            $lexer = new Lexer();
            $lexer->lex($resource);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    #[Test]
    public function itWillThrowIfStringLiteralFailsUnexpectedlyDuringEscaping(): void
    {
        $this->expectException(LexerFailure::class);
        $this->expectExceptionMessage('Unexpected end of input; expected an escaped character.');

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, '"hello\\');

        try {
            $lexer = new Lexer();
            $lexer->lex($resource);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}
