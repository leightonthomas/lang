<?php

declare(strict_types=1);

namespace Tests\Unit\Inference;

use App\Inference\Instantiator;
use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Instantiator::class)]
#[UsesClass(Variable::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(Application::class)]
class InstantiatorTest extends TestCase
{
    #[Test]
    public function itWillIncrementVariableNames(): void
    {
        $instance = new Instantiator();

        $a = $instance->newVariable();
        $b = $instance->newVariable();
        $c = $instance->newVariable();

        self::assertSame('x_0', $a->name);
        self::assertSame('x_1', $b->name);
        self::assertSame('x_2', $c->name);
    }

    #[Test]
    #[DataProvider('instantiationProvider')]
    public function itWillInstantiateCorrectly(
        Polytype $input,
        Monotype $expected,
    ): void {
        $instance = new Instantiator();

        self::assertEquals($expected, $instance($input));
    }

    public static function instantiationProvider(): array
    {
        return [
            [
                new Quantifier('z', new Variable('z')),
                new Variable('x_0'),
            ],
            [
                new Variable('z'),
                new Variable('z'),
            ],
            [
                new Application(
                    '_fn',
                    [
                        new Variable('x'),
                        new Variable('y'),
                        new Variable('z'),
                    ],
                ),
                new Application(
                    '_fn',
                    [
                        new Variable('x'),
                        new Variable('y'),
                        new Variable('z'),
                    ],
                ),
            ],
            [
                new Quantifier(
                    'z',
                    new Application(
                        '_fn',
                        [
                            new Variable('x'),
                            new Variable('y'),
                            new Variable('z'),
                        ],
                    ),
                ),
                new Application(
                    '_fn',
                    [
                        new Variable('x'),
                        new Variable('y'),
                        new Variable('x_0'),
                    ],
                ),
            ],
            [
                new Quantifier(
                    'x',
                    new Quantifier(
                        'y',
                        new Quantifier(
                            'z',
                            new Application(
                                '_fn',
                                [
                                    new Variable('a'),
                                    new Variable('x'),
                                    new Application(
                                        '_fn',
                                        [
                                            new Variable('y'),
                                            new Variable('b'),
                                        ],
                                    ),
                                    new Application('String', []),
                                    new Variable('y'),
                                    new Variable('z'),
                                    new Variable('c'),
                                ],
                            ),
                        ),
                    ),
                ),
                new Application(
                    '_fn',
                    [
                        new Variable('a'),
                        new Variable('x_0'),
                        new Application(
                            '_fn',
                            [
                                new Variable('x_1'),
                                new Variable('b'),
                            ],
                        ),
                        new Application('String', []),
                        new Variable('x_1'),
                        new Variable('x_2'),
                        new Variable('c'),
                    ],
                ),
            ],
        ];
    }
}
