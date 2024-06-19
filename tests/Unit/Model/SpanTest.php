<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Span;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Span::class)]
class SpanTest extends TestCase
{
    #[Test]
    public function itWillThrowOnConstructionIfEndLessThanStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Span; $start must be <= $end');

        new Span(10, 9);
    }
}
