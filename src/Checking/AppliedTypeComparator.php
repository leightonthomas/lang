<?php

declare(strict_types=1);

namespace App\Checking;

use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Variable;
use App\Model\Syntax\Simple\TypeAssignment;
use InvalidArgumentException;

use function count;
use function get_class;

readonly abstract class AppliedTypeComparator
{
    public static function isSame(
        Monotype $actual,
        TypeAssignment $expected,
    ): bool {
        if ($actual instanceof Variable) {
            return (
                ($actual->name === $expected->base->identifier)
                && (count($expected->arguments) === 0)
            );
        }

        if ($actual instanceof Application) {
            $sameTypeAndArgCount = (
                ($actual->constructor === $expected->base->identifier)
                && (count($actual->arguments) === count($expected->arguments))
            );

            if (! $sameTypeAndArgCount) {
                return false;
            }

            foreach ($actual->arguments as $index => $argument) {
                $expectedArgument = $expected->arguments[$index] ?? null;
                if ($expectedArgument === null) {
                    return false;
                }

                if (! self::isSame($argument, $expectedArgument)) {
                    return false;
                }
            }

            return true;
        }

        throw new InvalidArgumentException("Unhandled monotype: " . get_class($actual));
    }
}
