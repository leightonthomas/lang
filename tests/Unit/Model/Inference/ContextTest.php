<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Inference;

use App\Model\Inference\Context;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Context::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(Variable::class)]
class ContextTest extends TestCase
{
    #[Test]
    #[DataProvider('generalisationProvider')]
    public function itWillApplySubstitutionAsExpected(
        Context $context,
        Monotype $toGeneralise,
        Polytype $expected,
    ): void {
        $output = $context->generalise($toGeneralise);

        self::assertEquals($expected, $output);
    }

    public static function generalisationProvider(): array
    {
        return [
            [
                new Context(['x' => new Variable('y')]),
                new Variable('y'),
                new Variable('y'),
            ],
            [
                new Context(['x' => new Variable('z')]),
                new Variable('y'),
                // it's in the generalisation but not the context, so it gets quantified
                new Quantifier('y', new Variable('y')),
            ],
            [
                new Context(['x' => new Quantifier('a', new Variable('a'))]),
                // the above one is already a quantifier, so we still get one out despite having same name
                new Variable('a'),
                new Quantifier('a', new Variable('a')),
            ],
        ];
    }
}
