<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TypeChecker;

use App\Model\TypeChecker\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scope::class)]
class ScopeTest extends TestCase
{
    #[Test]
    public function itWillCorrectlyRetrieveAScopedVariable(): void
    {
        $scopeA = new Scope('a');
        $scopeA->addUnscopedVariable('foo');

        $scopeB = $scopeA->makeChildScope('b');
        $scopeB->addUnscopedVariable('bar');

        self::assertSame('a.foo', $scopeA->getScopedVariable('foo'));
        self::assertSame('a.foo', $scopeB->getScopedVariable('foo'));
        self::assertSame('a.b.bar', $scopeB->getScopedVariable('bar'));
        self::assertNull($scopeA->getScopedVariable('bar'));
    }
}
