<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference\Expression;

use App\Model\Inference\Expression\Application;
use App\Model\Inference\Expression\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Application::class)]
#[UsesClass(Variable::class)]
class ApplicationTest extends TestCase
{
    #[Test]
    public function itWillSerializeToJsonCorrectly(): void
    {
        $instance = new Application(new Variable('foo'), new Variable('bar'));

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'application',
                'left' => ['type' => 'variable', 'name' => 'foo'],
                'right' => ['type' => 'variable', 'name' => 'bar'],
            ]),
            json_encode($instance),
        );
    }
}
