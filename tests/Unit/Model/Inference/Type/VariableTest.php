<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Type;

use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Variable::class)]
#[UsesClass(Application::class)]
class VariableTest extends TestCase
{
    #[Test]
    public function itWillReturnCorrectFreeVariables(): void
    {
        self::assertSame(['a'], (new Variable('a'))->getFreeVariables());
    }

    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Variable('a');

        self::assertJsonStringEqualsJsonString(
            json_encode(['type' => 'variable', 'variable' => 'a']),
            json_encode($instance),
        );
    }

    #[Test]
    public function itWillDetermineIfAVariableEqualsAnotherMonotype(): void
    {
        $instance = new Variable('a');

        self::assertFalse($instance->equals(new Variable('b')));
        self::assertFalse($instance->equals(new Variable('')));
        self::assertTrue($instance->equals(new Variable('a')));
        self::assertFalse($instance->equals(new Variable('ab')));
        self::assertFalse($instance->equals(new Variable('a ')));
        self::assertFalse($instance->equals(new Application('foo', [])));
        self::assertFalse($instance->equals(new Application('foo', [new Variable('a')])));
    }

    #[Test]
    public function itWillDetermineIfAVariableContainsAnotherVariable(): void
    {
        $instance = new Variable('a');

        self::assertFalse($instance->contains(new Variable('b')));
        self::assertFalse($instance->contains(new Variable('')));
        self::assertTrue($instance->contains(new Variable('a')));
        self::assertFalse($instance->contains(new Variable('ab')));
        self::assertFalse($instance->contains(new Variable('a ')));
    }
}
