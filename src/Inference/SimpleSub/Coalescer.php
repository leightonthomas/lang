<?php

declare(strict_types=1);

namespace App\Inference\SimpleSub;

use App\Model\Inference\SimpleSub\Type\Coalesced\Funktion as FriendlyFunction;
use App\Model\Inference\SimpleSub\Type\Coalesced\Intersection as FriendlyIntersection;
use App\Model\Inference\SimpleSub\Type\Coalesced\PolarVariable;
use App\Model\Inference\SimpleSub\Type\Coalesced\Primitive as FriendlyPrimitive;
use App\Model\Inference\SimpleSub\Type\Coalesced\Record as FriendlyRecord;
use App\Model\Inference\SimpleSub\Type\Coalesced\Recursive as FriendlyRecursive;
use App\Model\Inference\SimpleSub\Type\Coalesced\Type as FriendlyType;
use App\Model\Inference\SimpleSub\Type\Coalesced\TypeVariable as FriendlyTypeVariable;
use App\Model\Inference\SimpleSub\Type\Coalesced\Union as FriendlyUnion;
use App\Model\Inference\SimpleSub\Type\Funktion as RawTypeFunction;
use App\Model\Inference\SimpleSub\Type\Primitive as RawTypePrimitive;
use App\Model\Inference\SimpleSub\Type\Record as RawTypeRecord;
use App\Model\Inference\SimpleSub\Type\SimpleType as RawSimpleType;
use App\Model\Inference\SimpleSub\Type\Variable as RawTypeVariable;
use RuntimeException;
use WeakMap;

use function array_map;

readonly class Coalescer
{
    public function coalesce(RawSimpleType $rawType): FriendlyType
    {
        return $this->_coalesce($rawType, new WeakMap());
    }

    /**
     * @param WeakMap<PolarVariable, string> $recursive
     */
    private function _coalesce(RawSimpleType $rawType, WeakMap $recursive): FriendlyType
    {
        return $this->go($rawType, true, $recursive, new WeakMap());
    }

    /**
     * @param WeakMap<PolarVariable, string> $recursive
     * @param WeakMap<PolarVariable, null> $inProcess
     */
    private function go(RawSimpleType $rawType, bool $polar, WeakMap $recursive, WeakMap $inProcess): FriendlyType
    {
        if ($rawType instanceof RawTypePrimitive) {
            return new FriendlyPrimitive($rawType->name);
        }

        if ($rawType instanceof RawTypeFunction) {
            return new FriendlyFunction(
                $this->go($rawType->lhs, ! $polar, $recursive, $inProcess),
                $this->go($rawType->rhs, $polar, $recursive, $inProcess),
            );
        }

        if ($rawType instanceof RawTypeRecord) {
            return new FriendlyRecord(
                array_map(
                    fn (RawSimpleType $rawFieldType) => $this->go($rawFieldType, $polar, $recursive, $inProcess),
                    $rawType->fields,
                ),
            );
        }

        if ($rawType instanceof RawTypeVariable) {
            $vsPolar = new PolarVariable($rawType->state, $polar);

            if ($inProcess->offsetExists($vsPolar)) {
                if (! $recursive->offsetExists($vsPolar)) {
                    $recursive[$vsPolar] = $rawType->state->uniqueName;
                }

                /** @var string $found we did an offset exists check and set the value if not, we know this exists */
                $found = $recursive[$vsPolar];

                return new FriendlyTypeVariable($found);
            }

            $bounds = $polar ? $rawType->state->lowerBounds : $rawType->state->upperBounds;

            /** @var list<RawSimpleType> $boundTypes */
            $boundTypes = [];
            foreach ($bounds as $boundsItem) {
                // mark vsPolar as in process to stop looping/exponential
                $inProcess[$vsPolar] = null;

                $boundTypes[] = $this->go(
                    $boundsItem,
                    $polar,
                    $recursive,
                    $inProcess,
                );
            }

            $foldedValue = new FriendlyTypeVariable($rawType->state->uniqueName);
            foreach ($boundTypes as $boundType) {
                $foldedValue = $polar
                    ? new FriendlyUnion($foldedValue, $boundType)
                    : new FriendlyIntersection($foldedValue, $boundType);
            }

            if (! $recursive->offsetExists($vsPolar)) {
                return $foldedValue;
            }

            return new FriendlyRecursive($recursive[$vsPolar], $foldedValue);
        }

        throw new RuntimeException("Unrecognised Type");
    }
}
