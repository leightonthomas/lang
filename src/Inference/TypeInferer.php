<?php

declare(strict_types=1);

namespace App\Inference;

use App\Model\Exception\Inference\FailedToInferType;
use App\Model\Inference\Context;
use App\Model\Inference\Expression\Abstraction;
use App\Model\Inference\Expression\Application;
use App\Model\Inference\Expression\Expression;
use App\Model\Inference\Expression\Let;
use App\Model\Inference\Expression\Variable;
use App\Model\Inference\Substitution;
use App\Model\Inference\Type\Application as ApplicationType;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Variable as VariableType;

use function count;
use function get_class;

/**
 * Type inference using Hindley-Milner algorithm W
 *
 * {@see https://www.youtube.com/@adam-jones} for implementation this is based off of
 */
final readonly class TypeInferer
{
    public const string FUNCTION_APPLICATION = '_fn';

    public function __construct(
        private Instantiator $instantiator,
    ) {
    }

    /**
     * @return array{0: Substitution, 1: Monotype}
     *
     * @throws FailedToInferType
     */
    public function infer(Context $context, Expression $expression): array
    {
        if ($expression instanceof Variable) {
            $value = $context[$expression->name] ?? throw new FailedToInferType(
                "Variable '$expression->name' does not exist",
            );

            return [new Substitution(), ($this->instantiator)($value)];
        }

        if ($expression instanceof Let) {
            [$sub1, $tau1] = $this->infer($context, $expression->value);
            [$sub2, $tau2] = $this->infer(
                $sub1->apply($context)->with($expression->variable, $context->generalise($tau1)),
                $expression->in,
            );

            return [$sub2->combine($sub1), $tau2];
        }

        if ($expression instanceof Abstraction) {
            $typeVar = $this->instantiator->newVariable();

            [$sub1, $tau1] = $this->infer(
                $context->copy()->with($expression->argument, $typeVar),
                $expression->expression,
            );

            return [
                $sub1,
                $sub1->apply(new ApplicationType(self::FUNCTION_APPLICATION, [$typeVar, $tau1])),
            ];
        }

        if ($expression instanceof Application) {
            [$sub1, $tau1] = $this->infer($context, $expression->left);
            [$sub2, $tau2] = $this->infer($sub1->apply($context), $expression->right);

            $typeVar = $this->instantiator->newVariable();

            $sub3 = $this->unify(
                $sub2->apply($tau1),
                new ApplicationType(self::FUNCTION_APPLICATION, [$tau2, $typeVar]),
            );

            return [$sub3->combine($sub2->combine($sub1)), $sub3->apply($typeVar)];
        }

        throw new FailedToInferType('Unrecognised expression type: ' . get_class($expression));
    }

    /**
     * @throws FailedToInferType
     */
    private function unify(Monotype $a, Monotype $b): Substitution
    {
        if (($a instanceof VariableType) && ($b instanceof VariableType) && $b->equals($a)) {
            // they're the same, we should add no substitutions
            return new Substitution();
        }

        if ($a instanceof VariableType) {
            if ($b->contains($a)) {
                throw new FailedToInferType('Recursive type dependency detected');
            }

            $substitutions = new Substitution();
            $substitutions[$a] = $b;

            return $substitutions;
        }

        if ($b instanceof VariableType) {
            return $this->unify($b, $a);
        }

        /**
         * We've deduced they're both applications
         *
         * @var ApplicationType $a
         * @var ApplicationType $b
         */
        if ($a->constructor !== $b->constructor) {
            throw new FailedToInferType(
                "Failed to unify types, different type constructors '$a->constructor' and '$b->constructor'",
            );
        }

        if (count($a->arguments) !== count($b->arguments)) {
            throw new FailedToInferType(
                'Failed to unify types, different argument lengths for type constructors'
            );
        }

        $substitutions = new Substitution();
        foreach ($a->arguments as $index => $aArgument) {
            $substitutions = $substitutions->combine(
                $this->unify(
                    $substitutions->apply($aArgument),
                    $substitutions->apply($b->arguments[$index]),
                ),
            );
        }

        return $substitutions;
    }
}
