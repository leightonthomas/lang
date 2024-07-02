<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Expression;

use App\Model\Inference\Expression\Abstraction;
use App\Model\Inference\Expression\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Abstraction::class)]
#[UsesClass(Variable::class)]
class AbstractionTest extends TestCase
{
    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Abstraction('foo', new Variable('bar'));

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'abstraction',
                'argument' => 'foo',
                'expression' => ['type' => 'variable', 'name' => 'bar'],
            ]),
            json_encode($instance),
        );
    }
}
