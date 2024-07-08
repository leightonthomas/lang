<?php

declare(strict_types=1);

namespace App\Checking;

use App\Compiler\Program;
use App\Model\Exception\TypeChecker\FailedTypeCheck;
use App\Model\Inference\Type\Application as TypeApplication;
use App\Model\Inference\Type\Variable as TypeVariable;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;

use function get_class;
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

            foreach ($function->codeBlock->expressions as $expression) {
                if (! ($expression instanceof BlockReturn)) {
                    continue;
                }

                $returnType = $program->getType($expression);
                if ($returnType === null) {
                    throw new FailedTypeCheck('Could not type-check return statement.');
                }

                $actualType = match (get_class($returnType)) {
                    TypeApplication::class => $returnType->constructor,
                    TypeVariable::class => $returnType->name,
                };

                if ($actualType === $function->assignedType->base->identifier) {
                    continue;
                }

                throw new FailedTypeCheck(
                    sprintf(
                        "Function \"%s\" was expected to have return type \"%s\", found \"%s\"",
                        $function->name->identifier,
                        $function->assignedType->base->identifier,
                        $actualType,
                    ),
                );
            }
        }
    }
}
