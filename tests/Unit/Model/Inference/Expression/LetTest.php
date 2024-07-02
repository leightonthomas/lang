<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Expression;

use App\Model\Inference\Expression\Let;
use App\Model\Inference\Expression\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Let::class)]
#[UsesClass(Variable::class)]
class LetTest extends TestCase
{
    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Let('foo', new Variable('bar'), new Variable('baz'));

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'let',
                'variable' => 'foo',
                'value' => ['type' => 'variable', 'name' => 'bar'],
                'in' => ['type' => 'variable', 'name' => 'baz'],
            ]),
            json_encode($instance),
        );
    }
}
