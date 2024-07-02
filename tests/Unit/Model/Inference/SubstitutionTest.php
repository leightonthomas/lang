<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference;

use App\Model\Inference\Context;
use App\Model\Inference\Substitution;
use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypeError;

use function json_encode;

#[CoversClass(Substitution::class)]
#[UsesClass(Variable::class)]
#[UsesClass(Application::class)]
class SubstitutionTest extends TestCase
{
    #[Test]
    #[DataProvider('substitutionProvider')]
    public function itWillApplySubstitutionAsExpected(
        Substitution $substitution,
        Monotype|Polytype|Context $input,
        Monotype|Polytype|Context $expected,
    ): void {
        $output = $substitution->apply($input);

        self::assertEquals($expected, $output);
    }

    public static function substitutionProvider(): array
    {
        return [
            [
                new Substitution(['x' => new Variable('y')]),
                new Variable('x'),
                new Variable('y'),
            ],
            [
                new Substitution(['z' => new Variable('y')]),
                new Variable('x'),
                new Variable('x'),
            ],
            [
                new Substitution(['x' => new Variable('y')]),
                new Application(
                    '_fn',
                    [
                        new Application('String', []),
                        new Variable('x'),
                    ],
                ),
                new Application(
                    '_fn',
                    [
                        new Application('String', []),
                        new Variable('y'),
                    ],
                ),
            ],
            [
                new Substitution(['x' => new Application('String', [])]),
                new Application(
                    '_fn',
                    [
                        new Application('String', []),
                        new Variable('x'),
                    ],
                ),
                new Application(
                    '_fn',
                    [
                        new Application('String', []),
                        new Application('String', []),
                    ],
                ),
            ],
            [
                new Substitution(['x' => new Variable('y')]),
                new Application(
                    '_fn',
                    [
                        new Application('String', []),
                        new Application('_fn', [new Variable('x')]),
                        new Variable('x'),
                    ],
                ),
                new Application(
                    '_fn',
                    [
                        new Application('String', []),
                        new Application('_fn', [new Variable('y')]),
                        new Variable('y'),
                    ],
                ),
            ],
        ];
    }

    #[Test]
    #[DataProvider('combinationProvider')]
    public function itWillCombineSubstitutionsAsExpected(
        Substitution $s1,
        Substitution $s2,
        Substitution $expected,
    ): void {
        $output = $s1->combine($s2);

        self::assertEquals($expected, $output);
    }

    public static function combinationProvider(): array
    {
        return [
            [
                new Substitution(['x' => new Variable('y')]),
                new Substitution(
                    [
                        'z' => new Application(
                            '_fn',
                            [
                                new Application('String', []),
                                new Variable('x'),
                            ],
                        ),
                    ],
                ),
                new Substitution(
                    [
                        'x' => new Variable('y'),
                        'z' => new Application(
                            '_fn',
                            [
                                new Application('String', []),
                                new Variable('y'),
                            ],
                        ),
                    ],
                ),
            ],
            [
                new Substitution(['x' => new Variable('y')]),
                new Substitution(['x' => new Variable('z')]),
                new Substitution(['x' => new Variable('z')]),
            ],
        ];
    }

    #[Test]
    public function itWillAllowSettingAndRetrievingValuesUsingArrayAccessOfStringOrVariable(): void
    {
        $subs = new Substitution();
        $subs[new Variable('a')] = new Variable('b');

        self::assertTrue($subs['a']->equals(new Variable('b')));
        self::assertTrue($subs[new Variable('a')]->equals(new Variable('b')));

        $subs['c'] = new Variable('d');

        self::assertTrue($subs['c']->equals(new Variable('d')));
        self::assertTrue($subs[new Variable('c')]->equals(new Variable('d')));
    }

    #[Test]
    public function itWillThrowIfOffsetIsNotAStringOrVariableWhenSettingValueViaArrayAccess(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Attempted to use a non-Variable for a substitution key');

        $subs = new Substitution();
        $subs[4] = new Variable('b');
    }

    #[Test]
    public function itWillThrowIfValueIsNotAMonotypeWhenSettingValueViaArrayAccess(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Attempted to use a non-Monotype for a substitution value');

        $subs = new Substitution();
        $subs['a'] = 'b';
    }

    #[Test]
    public function itWillAllowValuesToBeUnsetViaArrayAccess(): void
    {
        $subs = new Substitution();
        $subs[new Variable('a')] = new Variable('b');

        self::assertTrue($subs['a']->equals(new Variable('b')));

        unset($subs[new Variable('a')]);

        self::assertNull($subs['a'] ?? null);
        self::assertNull($subs[new Variable('a')] ?? null);

        $subs['c'] = new Variable('d');

        self::assertTrue($subs['c']->equals(new Variable('d')));

        unset($subs['c']);

        self::assertNull($subs['c'] ?? null);
        self::assertNull($subs[new Variable('c')] ?? null);
    }

    #[Test]
    public function itWillThrowIfOffsetIsNotStringOrVariableWhenUnsettingValueViaArrayAccess(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Attempted to use a non-Variable for a substitution key');

        $subs = new Substitution();
        $subs[new Variable('a')] = new Variable('b');

        self::assertTrue($subs['a']->equals(new Variable('b')));

        unset($subs[4]);
    }

    #[Test]
    public function itWillJsonSerializeCorrectly(): void
    {
        $subs = new Substitution();
        $subs[new Variable('a')] = new Variable('b');
        $subs[new Variable('c')] = new Application('d', [new Variable('e')]);

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'a' => ['type' => 'variable', 'variable' => 'b'],
                'c' => [
                    'type' => 'application',
                    'constructor' => 'd',
                    'arguments' => [
                        ['type' => 'variable', 'variable' => 'e'],
                    ],
                ],
            ]),
            json_encode($subs),
        );
    }
}
