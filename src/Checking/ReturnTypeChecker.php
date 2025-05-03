<?php

declare(strict_types=1);

namespace App\Checking;

use App\Compiler\Program;
use App\Model\Exception\TypeChecker\FailedTypeCheck;
use App\Model\StandardType;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;

use function sprintf;

class ReturnTypeChecker
{
    /**
     * @throws FailedTypeCheck
     */
    public function check(Program $program): void
    {
        foreach ($program->getFunctions() as $programFunction) {
            $function = $programFunction->rawFunction;
            if (! ($function instanceof FunctionDefinition)) {
                continue;
            }

            $hadReturn = false;
            foreach ($function->codeBlock->expressions as $expression) {
                if (! ($expression instanceof BlockReturn)) {
                    continue;
                }

                $hadReturn = true;

                $returnType = $program->getType($expression);
                if ($returnType === null) {
                    throw new FailedTypeCheck('Could not type-check return statement.');
                }

                if (! AppliedTypeComparator::isSame($returnType, $function->assignedType)) {
                    throw new FailedTypeCheck(
                        sprintf(
                            "Function \"%s\" was expected to have return type \"%s\", found \"%s\"",
                            $function->name->identifier,
                            $function->assignedType,
                            $returnType,
                        ),
                    );
                }
            }

            $isUnitReturnType = $function->assignedType->base->identifier === StandardType::UNIT->value;

            if ((! $hadReturn) && ( ! $isUnitReturnType)) {
                throw new FailedTypeCheck(
                    sprintf(
                        "Function \"%s\" does not return declared type \"%s\"",
                        $function->name->identifier,
                        $function->assignedType->base->identifier,
                    ),
                );
            }
        }
    }
}
