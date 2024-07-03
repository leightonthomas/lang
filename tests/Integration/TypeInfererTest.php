<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Inference\Instantiator;
use App\Inference\TypeInferer;
use App\Model\Inference\Context;
use App\Model\Inference\Expression\Abstraction;
use App\Model\Inference\Expression\Application;
use App\Model\Inference\Expression\Expression;
use App\Model\Inference\Expression\Let;
use App\Model\Inference\Expression\Variable;
use App\Model\Inference\Substitution;
use App\Model\Inference\Type\Application as ApplicationType;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Quantifier as QuantifierType;
use App\Model\Inference\Type\Variable as VariableType;
use App\Model\StandardType;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(TypeInferer::class)]
#[UsesClass(Instantiator::class)]
#[UsesClass(Context::class)]
#[UsesClass(Substitution::class)]
#[UsesClass(ApplicationType::class)]
#[UsesClass(VariableType::class)]
#[UsesClass(QuantifierType::class)]
#[UsesClass(Abstraction::class)]
#[UsesClass(Variable::class)]
#[UsesClass(Application::class)]
class TypeInfererTest extends TestCase
{
    #[Test]
    #[DataProvider('inferenceProvider')]
    public function itWillInferTypesCorrectly(
        Context $context,
        Expression $expression,
        Substitution $expectedSubs,
        Monotype $expectedType,
    ): void {
        $inferer = new TypeInferer(new Instantiator());
        [$actualSubs, $actualType] = $inferer->infer($context, $expression);

        self::assertEquals($expectedSubs, $actualSubs);
        self::assertEquals($expectedType, $actualType, json_encode($actualSubs));
    }

    public static function inferenceProvider(): array
    {
        $boolIntContext = new Context(
            [
                'odd' => new ApplicationType(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new ApplicationType('int', []),
                        new ApplicationType('bool', []),
                    ],
                ),
                'add' => new ApplicationType(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new ApplicationType('int', []),
                        new ApplicationType(
                            StandardType::FUNCTION_APPLICATION,
                            [
                                new ApplicationType('int', []),
                                new ApplicationType('int', []),
                            ],
                        ),
                    ],
                ),
                'not' => new ApplicationType(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new ApplicationType('bool', []),
                        new ApplicationType('bool', []),
                    ],
                ),
                'true' => new ApplicationType('bool', []),
                'false' => new ApplicationType('bool', []),
                'one' => new ApplicationType('int', []),
            ],
        );

        return [
            'Simple abstraction' => [
                new Context(),
                new Abstraction('x', new Variable('x')),
                new Substitution(),
                new ApplicationType(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new VariableType('x_0'),
                        new VariableType('x_0'),
                    ],
                ),
            ],
            'Pre-defined context #1' => [
                $boolIntContext,
                new Variable('true'),
                new Substitution(),
                new ApplicationType('bool', []),
            ],
            'Pre-defined context #2' => [
                $boolIntContext,
                new Application(new Variable('not'), new Variable('true')),
                new Substitution(['x_0' => new ApplicationType('bool', [])]),
                new ApplicationType('bool', []),
            ],
            'Pre-defined context #3' => [
                $boolIntContext,
                new Variable('one'),
                new Substitution(),
                new ApplicationType('int', []),
            ],
            'Pre-defined context #4' => [
                $boolIntContext,
                new Application(new Variable('not'), new Application(new Variable('odd'), new Variable('one'))),
                new Substitution(
                    [
                        'x_0' => new ApplicationType('bool', []),
                        'x_1' => new ApplicationType('bool', []),
                    ],
                ),
                new ApplicationType('bool', []),
            ],
            'Pre-defined context #5' => [
                $boolIntContext,
                new Let(
                    'id',
                    new Abstraction('x', new Variable('x')),
                    new Application(
                        new Application(new Variable('id'), new Variable('odd')),
                        new Application(new Variable('id'), new Variable('one')),
                    ),
                ),
                new Substitution(
                    [
                        'x_7' => new ApplicationType('bool', []),
                        'x_5' => new ApplicationType('int', []),
                        'x_6' => new ApplicationType('int', []),
                        'x_2' => new ApplicationType(
                            StandardType::FUNCTION_APPLICATION,
                            [
                                new ApplicationType('int', []),
                                new ApplicationType('bool', []),
                            ],
                        ),
                        'x_3' => new ApplicationType(
                            StandardType::FUNCTION_APPLICATION,
                            [
                                new ApplicationType('int', []),
                                new ApplicationType('bool', []),
                            ],
                        ),
                    ],
                ),
                new ApplicationType('bool', []),
            ],
            'Pre-defined context #6' => [
                $boolIntContext,
                new Application(
                    new Application(new Variable('add'), new Variable('one')),
                    new Variable('one'),
                ),
                new Substitution(
                    [
                        'x_1' => new ApplicationType('int', []),
                        'x_0' => new ApplicationType(
                            StandardType::FUNCTION_APPLICATION,
                            [
                                new ApplicationType('int', []),
                                new ApplicationType('int', []),
                            ],
                        ),
                    ],
                ),
                new ApplicationType('int', []),
            ],
            'Pre-defined context #7' => [
                $boolIntContext,
                new Let(
                    'plusone',
                    new Application(new Variable('add'), new Variable('one')),
                    new Application(new Variable('plusone'), new Variable('one')),
                ),
                new Substitution(
                    [
                        'x_0' => new ApplicationType(
                            StandardType::FUNCTION_APPLICATION,
                            [
                                new ApplicationType('int', []),
                                new ApplicationType('int', []),
                            ],
                        ),
                        'x_1' => new ApplicationType('int', []),
                    ],
                ),
                new ApplicationType('int', []),
            ],
            'Pre-defined context #8' => [
                $boolIntContext,
                new Let(
                    'plusone',
                    new Application(new Variable('add'), new Variable('one')),
                    new Variable('plusone'),
                ),
                new Substitution(
                    [
                        'x_0' => new ApplicationType(
                            StandardType::FUNCTION_APPLICATION,
                            [
                                new ApplicationType('int', []),
                                new ApplicationType('int', []),
                            ],
                        ),
                    ],
                ),
                new ApplicationType(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new ApplicationType('int', []),
                        new ApplicationType('int', []),
                    ],
                ),
            ],
        ];
    }

    #[Test]
    public function itWillThrowIfItEncountersAnUndefinedVariable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Variable 'y' does not exist");

        $inferer = new TypeInferer(new Instantiator());
        // x is bound as an argument basically, but y is free-floating and undefined
        $inferer->infer(new Context(), new Abstraction('x', new Variable('y')));
    }

    #[Test]
    public function itWillThrowIfItEncountersAnUnrecognisedExpressionType(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/Unrecognised expression type/");

        $inferer = new TypeInferer(new Instantiator());
        $inferer->infer(
            new Context(),
            new class implements Expression {
                public function jsonSerialize(): array
                {
                    return [];
                }
            },
        );
    }

    #[Test]
    public function itWillThrowIfTryingToUnifyTwoDifferentTypeConstructors(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/Failed to unify types, different type constructors/");

        $inferer = new TypeInferer(new Instantiator());
        $inferer->infer(
            new Context(
                [
                    'a' => new ApplicationType(
                        'a',
                        [
                            new ApplicationType('int', []),
                            new ApplicationType('bool', []),
                        ],
                    ),
                    'b' => new ApplicationType(
                        'b',
                        [
                            new ApplicationType('int', []),
                            new ApplicationType('bool', []),
                        ],
                    ),
                ],
            ),
            new Application(new Variable('a'), new Variable('b')),
        );
    }
}
