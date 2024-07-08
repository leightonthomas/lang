<?php

declare(strict_types=1);

namespace App\Compiler;

use App\Lexer\Token\Identifier;
use App\Model\Compiler\CustomBytecode\Standard\Function\StandardFunction;
use App\Model\Inference\Context;
use App\Model\Inference\Type\Monotype;
use App\Model\Syntax\Expression;
use App\Model\Syntax\Simple\TypeAssignment;
use App\Parser\ParsedOutput;
use RuntimeException;
use WeakMap;

use function sprintf;

final class Program
{
    /** @var array<string, ProgramFunction> */
    private array $functions;

    public function __construct(
        private readonly ParsedOutput $parsedOutput,
        private readonly Context $context,
        /** @var WeakMap<Expression, Monotype> */
        private readonly WeakMap $types,
    ) {
        $this->functions = [];

        /** @var class-string<StandardFunction> $standardFunction */
        foreach (StandardFunction::FUNCTIONS as $standardFunction) {
            $returnType = $this->context->attemptTypeResolution($standardFunction::getReturnType()->value);
            if (! ($returnType instanceof Monotype)) {
                throw new RuntimeException(
                    sprintf(
                        "StandardFunction '%s' does not have a return type that resolves to a Monotype",
                        $standardFunction,
                    ),
                );
            }

            /** @var array<string, Monotype> $programArguments */
            $programArguments = [];
            foreach ($standardFunction::getArguments() as $argumentName => $argument) {
                $resolvedType = $this->context->attemptTypeResolution($argument->value);
                if (! ($resolvedType instanceof Monotype)) {
                    throw new RuntimeException(
                        sprintf(
                            "StandardFunction '%s' argument '%s' does not resolve to a Monotype",
                            $standardFunction,
                            $argumentName,
                        ),
                    );
                }

                $programArguments[$argumentName] = $resolvedType;
            }

            $this->functions[$standardFunction::getName()] = new ProgramFunction(
                $standardFunction::getName(),
                $programArguments,
                $returnType,
                rawFunction: $standardFunction,
            );
        }

        foreach ($this->parsedOutput->functions as $functionName => $function) {
            $returnType = $this->context->attemptTypeResolution($function->assignedType->base->identifier);
            if (! ($returnType instanceof Monotype)) {
                throw new RuntimeException(
                    sprintf(
                        "Function '%s' does not have a return type that resolves to a Monotype",
                        $functionName,
                    ),
                );
            }

            /** @var array<string, Monotype> $programArguments */
            $programArguments = [];

            /**
             * @var Identifier $name
             * @var TypeAssignment $type
             */
            foreach ($function->arguments as ['name' => $name, 'type' => $type]) {
                $resolvedType = $this->context->attemptTypeResolution($type->base->identifier);
                if (! ($resolvedType instanceof Monotype)) {
                    throw new RuntimeException(
                        sprintf(
                            "StandardFunction '%s' argument '%s' does not resolve to a Monotype",
                            $standardFunction,
                            $name->identifier,
                        ),
                    );
                }

                $programArguments[$name->identifier] = $resolvedType;
            }

            $this->functions[$functionName] = new ProgramFunction(
                $functionName,
                $programArguments,
                $returnType,
                rawFunction: $function,
            );
        }
    }

    /**
     * @return array<string, ProgramFunction>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function getType(Expression $expression): ?Monotype
    {
        return $this->types[$expression] ?? null;
    }

    public function getFunction(string $name): ?ProgramFunction
    {
        return $this->functions[$name] ?? null;
    }
}
