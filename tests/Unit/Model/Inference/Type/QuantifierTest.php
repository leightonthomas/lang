<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Type;

use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Quantifier::class)]
#[UsesClass(Variable::class)]
#[UsesClass(Application::class)]
class QuantifierTest extends TestCase
{
    #[Test]
    #[DataProvider('freeVariablesProvider')]
    public function itWillReturnCorrectFreeVariables(Quantifier $application, array $expected): void
    {
        self::assertSame($expected, $application->getFreeVariables());
    }

    public static function freeVariablesProvider(): array
    {
        return [
            [
                new Quantifier(
                    'a',
                    new Quantifier(
                        'b',
                        new Application(
                            'c',
                            [new Variable('d')],
                        ),
                    ),
                ),
                ['d'],
            ],
            [
                new Quantifier(
                    'a',
                    new Application(
                        'c',
                        [new Variable('d')],
                    ),
                ),
                ['d'],
            ],
            [
                new Quantifier('a', new Variable('d')),
                ['d'],
            ],
        ];
    }

    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Quantifier(
            'a',
            new Quantifier(
                'b',
                new Application(
                    'c',
                    [new Variable('d')],
                ),
            ),
        );

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'quantifier',
                'quantified' => 'a',
                'body' => [
                    'type' => 'quantifier',
                    'quantified' => 'b',
                    'body' => [
                        'type' => 'application',
                        'constructor' => 'c',
                        'arguments' => [
                            ['type' => 'variable', 'variable' => 'd'],
                        ],
                    ],
                ],
            ]),
            json_encode($instance),
        );
    }
}
