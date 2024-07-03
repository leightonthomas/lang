<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Type;

use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Variable;
use App\Model\StandardType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Application::class)]
#[UsesClass(Variable::class)]
class ApplicationTest extends TestCase
{
    #[Test]
    #[DataProvider('freeVariablesProvider')]
    public function itWillReturnCorrectFreeVariables(Application $application, array $expected): void
    {
        self::assertSame($expected, $application->getFreeVariables());
    }

    public static function freeVariablesProvider(): array
    {
        return [
            [
                new Application(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new Variable('x_0'),
                        new Variable('x_0'),
                    ],
                ),
                ['x_0', 'x_0'],
            ],
        ];
    }

    #[Test]
    public function itWillDetermineIfItContainsAnotherVariable(): void
    {
        $instance = new Application(
            StandardType::FUNCTION_APPLICATION,
            [
                new Variable('x_0'),
                new Variable('x_1'),
            ],
        );

        self::assertFalse($instance->contains(new Variable(StandardType::FUNCTION_APPLICATION->value)));
        self::assertTrue($instance->contains(new Variable('x_0')));
        self::assertTrue($instance->contains(new Variable('x_1')));
        self::assertFalse($instance->contains(new Variable('x_2')));
        self::assertFalse($instance->contains(new Variable('x_')));
        self::assertFalse($instance->contains(new Variable('x')));
        self::assertFalse($instance->contains(new Variable('')));
    }

    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Application(
            StandardType::FUNCTION_APPLICATION,
            [
                new Variable('x_0'),
                new Variable('x_1'),
            ],
        );

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'application',
                'constructor' => '_fn',
                'arguments' => [
                    ['type' => 'variable', 'variable' => 'x_0'],
                    ['type' => 'variable', 'variable' => 'x_1'],
                ],
            ]),
            json_encode($instance),
        );
    }

    #[Test]
    #[DataProvider('equalProvider')]
    public function itWillDetermineIfApplicationEqualsAnotherMonotype(
        Monotype $other,
        bool $expected,
    ): void {
        $application = new Application(
            StandardType::FUNCTION_APPLICATION,
            [
                new Variable('x_0'),
                new Variable('x_1'),
            ],
        );

        self::assertSame($expected, $application->equals($other));
    }

    public static function equalProvider(): array
    {
        return [
            [
                new Variable('x_0'),
                false,
            ],
            [
                new Variable('x_1'),
                false,
            ],
            [
                new Variable(StandardType::FUNCTION_APPLICATION->value),
                false,
            ],
            [
                new Application(StandardType::FUNCTION_APPLICATION, []),
                false,
            ],
            [
                new Application(StandardType::FUNCTION_APPLICATION, [new Variable('x_0')]),
                false,
            ],
            [
                new Application('other', [new Variable('x_0'), new Variable('x_1')]),
                false,
            ],
            [
                new Application(StandardType::FUNCTION_APPLICATION, [new Variable('x_0'), new Variable('x_2')]),
                false,
            ],
            [
                new Application(
                    StandardType::FUNCTION_APPLICATION,
                    [new Variable('x_0'), new Variable('x_1'), new Variable('x_2')],
                ),
                false,
            ],
            [
                new Application(StandardType::FUNCTION_APPLICATION, [new Variable('x_0'), new Variable('x_1')]),
                true,
            ],
        ];
    }
}
