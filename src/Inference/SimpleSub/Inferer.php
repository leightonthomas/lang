<?php

declare(strict_types=1);

namespace App\Inference\SimpleSub;

use App\Model\Inference\SimpleSub\Context;
use App\Model\Inference\SimpleSub\Term\Application as TermApplication;
use App\Model\Inference\SimpleSub\Term\Lambda as TermLambda;
use App\Model\Inference\SimpleSub\Term\Literal as TermLiteral;
use App\Model\Inference\SimpleSub\Term\Record as TermRecord;
use App\Model\Inference\SimpleSub\Term\Selector as TermSelector;
use App\Model\Inference\SimpleSub\Term\Term;
use App\Model\Inference\SimpleSub\Term\Variable as TermVariable;
use App\Model\Inference\SimpleSub\Type\Funktion as TypeFunction;
use App\Model\Inference\SimpleSub\Type\Primitive as TypePrimitive;
use App\Model\Inference\SimpleSub\Type\Record as TypeRecord;
use App\Model\Inference\SimpleSub\Type\SimpleType;
use App\Model\Inference\SimpleSub\Type\Variable as TypeVariable;
use App\Model\Inference\SimpleSub\Type\VariableState;
use RuntimeException;
use WeakMap;

use function array_map;
use function get_class;
use function str_repeat;

/**
 * Based on the paper:
 * The Simple Essence of Algebraic Subtyping: Principal Type Inference with Subtyping Made Easy
 * by Parreaux, Lionel
 */
class Inferer
{
    private const bool DEBUG_LOG = false;
    private const bool DEBUG_NAMING = false;

    private int $varCounter = 0;
    private int $depth = -1;

    public function inferType(Term $term): SimpleType
    {
        $context = new Context();

        return $this->infer($term, $context);
    }

    private function infer(Term $term, Context $context): SimpleType
    {
        $this->depth++;
        $this->log("($this->depth) typeTerm($term)($context)", true);
        $res = $this->_infer($term, $context);

        $this->log("($this->depth) = $res\n");

        $this->depth--;

        return $res;
    }

    private function _infer(Term $term, Context $context): SimpleType
    {

        if ($term instanceof TermLiteral) {
            return new TypePrimitive($term->type);
        }

        if ($term instanceof TermVariable) {
            return $context[$term->name] ?? throw new RuntimeException("Variable not found in context: '$term->name'");
        }

        if ($term instanceof TermRecord) {
            return new TypeRecord(
                array_map(
                    fn (Term $field) => $this->infer($field, $context),
                    $term->fields,
                ),
            );
        }

        if ($term instanceof TermLambda) {
            $parameter = $this->freshTypeVariable();

            return new TypeFunction(
                $parameter,
                $this->infer($term->rhs, $context->with($term->name, $parameter)),
            );
        }

        if ($term instanceof TermApplication) {
            $typedLhs = $this->infer($term->lhs, $context);
            $typedRhs = $this->infer($term->rhs, $context);

            $returnType = $this->freshTypeVariable();
            $this->constrain(
                $typedLhs,
                new TypeFunction($typedRhs, $returnType),
                new WeakMap(),
            );

            return $returnType;
        }

        if ($term instanceof TermSelector) {
            $returnType = $this->freshTypeVariable();

            $this->constrain(
                $this->infer($term->receiver, $context),
                new TypeRecord([$term->field => $returnType]),
                new WeakMap(),
            );

            return $returnType;
        }

        throw new RuntimeException("Unhandled Term: " . get_class($term));
    }

    private function constrain(SimpleType $lhs, SimpleType $rhs, WeakMap $cache): void
    {
        $this->depth++;
        $this->log("constrain($lhs, $rhs)", true);

        $this->_constrain($lhs, $rhs, $cache);

        $this->depth--;
    }
    /**
     * @param WeakMap<SimpleType, WeakMap<SimpleType, null>> $cache
     */
    private function _constrain(SimpleType $lhs, SimpleType $rhs, WeakMap $cache): void
    {
        // TODO different semantics between PHP & Scala here? is (A, B) same as (B, A) for purposes of the set?
        if ($cache->offsetExists($lhs) && $cache[$lhs]->offsetExists($rhs)) {
            return;
        }

        // add cache entry for specific $lhs
        $topCacheEntry = $cache[$lhs] ?? new WeakMap();
        $topCacheEntry[$rhs] = null;

        // overwrite on the top cache in case it didn't exist before
        $cache[$lhs] = $topCacheEntry;

        if (
            ($lhs instanceof TypePrimitive)
            && ($rhs instanceof TypePrimitive)
            && ($lhs->name === $rhs->name)
        ) {
            // nothing to do
            return;
        }

        if (
            ($lhs instanceof TypeFunction)
            && ($rhs instanceof TypeFunction)
        ) {
            $this->constrain($rhs->lhs, $lhs->lhs, $cache);
            $this->constrain($lhs->rhs, $rhs->rhs, $cache);

            return;
        }

        if (
            ($lhs instanceof TypeRecord)
            && ($rhs instanceof TypeRecord)
        ) {
            foreach ($rhs->fields as $rhsFieldName => $rhsFieldValue) {
                $lhsFieldValue = $lhs[$rhsFieldName] ?? throw new RuntimeException(
                    "Missing field '$rhsFieldValue' in left-hand-side Record",
                );

                $this->constrain($lhsFieldValue, $rhsFieldValue, $cache);
            }

            return;
        }

        if ($lhs instanceof TypeVariable) {
            // modify LHS upper bounds in-place explicitly
            $lhs->state->upperBounds = [$rhs, ...$lhs->state->upperBounds];

            $this->log("$lhs.upperBounds :: $rhs");

            foreach ($lhs->state->lowerBounds as $lowerBoundType) {
                $this->constrain($lowerBoundType, $rhs, $cache);
            }

            return;
        }

        if ($rhs instanceof TypeVariable) {
            // modify RHS lower bounds in-place explicitly
            $rhs->state->lowerBounds = [$lhs, ...$rhs->state->lowerBounds];
            foreach ($rhs->state->upperBounds as $upperBoundType) {
                $this->constrain($lhs, $upperBoundType, $cache);
            }

            return;
        }

        throw new RuntimeException("Failed to constrain types");
    }

    private function freshTypeVariable(): TypeVariable
    {
        $varNumber = $this->varCounter;
        $this->varCounter++;

        $map = [
            'α',
            'β',
            'γ',
            'δ',
            'ε',
        ];

        if (self::DEBUG_NAMING) {
            $uniqueName = $map[$varNumber] ?? "x_$varNumber";
        } else {
            $uniqueName = "x_$varNumber";
        }

        $this->log(" // fresh $uniqueName");

        return new TypeVariable(
            new VariableState(uniqueName: $uniqueName, lowerBounds: [], upperBounds: []),
        );
    }

    /**
     * Produces output that vaguely matches the paper for debugging
     */
    private function log(string $string, bool $newline = false)
    {
        if (self::DEBUG_LOG) {
            echo ($newline ? "\n" : "") . str_repeat("    ", $this->depth) . $string;
        }
    }
}
