<?php

declare(strict_types=1);

namespace Tests\Integration\Inference;

use App\Inference\SimpleSub\Coalescer;
use App\Inference\SimpleSub\Inferer;
use App\Model\Inference\SimpleSub\Term\Application;
use App\Model\Inference\SimpleSub\Term\Lambda;
use App\Model\Inference\SimpleSub\Term\Literal;
use App\Model\Inference\SimpleSub\Term\Term;
use App\Model\Inference\SimpleSub\Term\Variable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SimpleSubTest extends TestCase
{
    private Inferer $inferer;
    private Coalescer $coalescer;

    protected function setUp(): void
    {
        $this->inferer = new Inferer();
        $this->coalescer = new Coalescer();
    }

    #[Test]
    #[DataProvider('typingProvider')]
    public function itWorks(Term $term, string $expectedCoalesced): void
    {
        $inferred = $this->inferer->inferType($term);
        $result = $this->coalescer->coalesce($inferred);

        self::assertSame($expectedCoalesced, (string) $result);
    }

    public static function typingProvider(): array
    {
        // TODO this only partially matches?
        return [
            [
                new Lambda('𝑓', new Lambda('𝑥', new Application(new Variable('𝑓'), new Application(new Variable('𝑓'), new Variable('𝑥'))))),
                '((T(x_0) ⊓ T(x_2) -> T(x_3)) ⊓ T(x_1) -> T(x_2)) -> T(x_1) -> T(x_3)',
            ],
            [new Literal("int"), "int"],
            [new Lambda("x", new Literal("int")), "T(x_0) -> int"],
            [new Lambda("x", new Variable("x")), "T(x_0) -> T(x_0)"],
            [new Lambda("x", new Application(new Variable('x'), new Literal('int'))), "(T(x_0) ⊓ int -> T(x_1)) -> T(x_1)"],
        ];
    }
}


