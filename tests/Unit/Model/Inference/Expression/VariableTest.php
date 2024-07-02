<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Expression;

use App\Model\Inference\Expression\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Variable::class)]
class VariableTest extends TestCase
{
    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Variable('a');

        self::assertJsonStringEqualsJsonString(
            json_encode(['type' => 'variable', 'name' => 'a']),
            json_encode($instance),
        );
    }
}
