<?php

declare(strict_types=1);

namespace App;

use App\Inference\TypeInferer;
use App\Model\Compiler\CustomBytecode\Standard\Function\StandardFunction;
use App\Model\Exception\Inference\FailedToInferType;
use App\Model\Exception\TypeChecker\FailedTypeCheck;
use App\Model\Inference\Context;
use App\Model\Inference\Expression\Application as HindleyApplication;
use App\Model\Inference\Expression\Expression as HindleyExpression;
use App\Model\Inference\Expression\Let as HindleyLet;
use App\Model\Inference\Expression\Variable as HindleyVariable;
use App\Model\Inference\Type\Application as TypeApplication;
use App\Model\Inference\Type\Variable as TypeVariable;
use App\Model\StandardType;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Definition\VariableDefinition as SyntaxVariableDefinition;
use App\Model\Syntax\Simple\Infix\Addition;
use App\Model\Syntax\Simple\Infix\FunctionCall;
use App\Model\Syntax\Simple\Infix\Subtraction;
use App\Model\Syntax\Simple\IntegerLiteral as SyntaxIntegerLiteral;
use App\Model\Syntax\Simple\Prefix\Group;
use App\Model\Syntax\Simple\Prefix\Prefix;
use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\Simple\StringLiteral as SyntaxStringLiteral;
use App\Model\Syntax\Simple\Variable;
use App\Model\Syntax\Simple\Variable as SyntaxVariable;
use App\Model\TypeChecker\Scope;
use App\Parser\ParsedOutput;

use function array_reverse;
use function get_class;
use function sprintf;

final class TypeChecker
{
    private int $letExprCounter;

    public function __construct(
        private readonly TypeInferer $typeInferer,
    ) {
        $this->letExprCounter = 0;
    }

    /**
     * The goal with this is to convert all expressions (bottom-up) into a single Hindley-Milner expression.
     *
     * @throws FailedTypeCheck
     */
    public function checkTypes(ParsedOutput $parsedOutput): void
    {
        $context = new Context([
            StandardType::STRING->value => new TypeApplication(StandardType::STRING->value, []),
            StandardType::INT->value => new TypeApplication(StandardType::INT->value, []),
            StandardType::BOOL->value => new TypeApplication(StandardType::BOOL->value, []),
            'true' => new TypeApplication(StandardType::BOOL->value, []),
            'false' => new TypeApplication(StandardType::BOOL->value, []),
            StandardType::UNIT->value => new TypeApplication(StandardType::UNIT->value, []),
            StandardType::INT_ADDITION->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeApplication(StandardType::INT->value, []),
                            new TypeApplication(StandardType::INT->value, []),
                        ],
                    ),
                ],
            ),
            StandardType::INT_SUBTRACTION->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeApplication(StandardType::INT->value, []),
                            new TypeApplication(StandardType::INT->value, []),
                        ],
                    ),
                ],
            ),
        ]);

        $globalScope = new Scope('');

        /** @var class-string<StandardFunction> $standardFunction */
        foreach (StandardFunction::FUNCTIONS as $standardFunction) {
            $globalScope->addUnscopedVariable($standardFunction::getName());

            $fnExpression = $context->variableOrExisting($standardFunction::getReturnType());

            foreach (array_reverse($standardFunction::getArguments()) as $type) {
                $fnExpression = new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [$context->variableOrExisting($type->value), $fnExpression],
                );
            }

            $context[$standardFunction::getName()] = $fnExpression;
        }

        // functions require a type to be set up-front, so we can add that to the global context
        foreach ($parsedOutput->functions as $function) {
            // start off with just the return type of the function, that way we can build on it for each arg
            // and wrap it in an application, or if it has 0 arguments it's valid as just an alias to the return type
            $fnExpression = $context->variableOrExisting($function->assignedType->base->identifier);
            foreach (array_reverse($function->arguments) as ['type' => $type]) {
                $fnExpression = new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        $context->variableOrExisting($type->base->identifier),
                        $fnExpression,
                    ],
                );
            }

            $context[$function->name->identifier] = $fnExpression;
            $globalScope->addUnscopedVariable($function->name->identifier);
        }

        foreach ($parsedOutput->functions as $function) {
            $fnScope = $globalScope->makeChildScope($function->name->identifier);
            foreach ($function->arguments as ['name' => $name, 'type' => $type]) {
                $fnScope->addUnscopedVariable($name->identifier);
                $context[$fnScope->getScopedVariable($name->identifier)] = $context->variableOrExisting(
                    $type->base->identifier,
                );
            }

            foreach ($function->codeBlock->expressions as $expression) {
                $hindleyExpression = $this->convertToHindleyExpression($fnScope, $expression);

                try {
                    $inferenceResult = $this->typeInferer->infer($context, $hindleyExpression);

                    $parsedOutput->addType($expression, $inferenceResult[1]);
                } catch (FailedToInferType $e) {
                    throw new FailedTypeCheck("Failed to infer types", 0, $e);
                }

                if ($expression instanceof SyntaxVariableDefinition) {
                    $scopedVarName = $fnScope->getScopedVariable($expression->name->identifier);
                    $actualVarType = $parsedOutput->getType($expression);
                    if ($actualVarType === null) {
                        throw new FailedTypeCheck("Expected to have gotten a type for this variable by now");
                    }

                    $context[$scopedVarName] = $actualVarType;
                }
            }

            // we have to reverse this so that the let expressions work as intended
            foreach ($function->codeBlock->expressions as $expression) {
                if (! ($expression instanceof BlockReturn)) {
                    continue;
                }

                $returnType = $parsedOutput->getType($expression);
                if ($returnType === null) {
                    throw new FailedTypeCheck('Could not type-check return statement.');
                }

                $actualType = match (get_class($returnType)) {
                    TypeApplication::class => $returnType->constructor,
                    TypeVariable::class => $returnType->name,
                };

                if ($actualType !== $function->assignedType->base->identifier) {
                    throw new FailedTypeCheck(
                        sprintf(
                            "Function \"%s\" was expected to have return type \"%s\", found \"%s\"",
                            $fnScope->getScopedName(),
                            $function->assignedType->base->identifier,
                            $actualType,
                        ),
                    );
                }
            }
        }
    }

    /**
     * @throws FailedTypeCheck
     */
    private function convertToHindleyExpression(
        Scope $scope,
        SimpleSyntax $syntax,
        ?HindleyExpression $previousExpression = null,
    ): HindleyExpression {
        if ($syntax instanceof SyntaxStringLiteral) {
            return new HindleyVariable(StandardType::STRING->value);
        }

        if ($syntax instanceof SyntaxIntegerLiteral) {
            return new HindleyVariable(StandardType::INT->value);
        }

        // since we don't care about runtime-level types we can just give the same type as the operand, since it'll
        // never change based on it having a prefix (even true & false resolve to bool)
        if ($syntax instanceof Prefix) {
            return $this->convertToHindleyExpression($scope, $syntax->operand, $previousExpression);
        }

        if ($syntax instanceof SyntaxVariable) {
            // see if it already exists in a known scope first, fall back to making a temporary one otherwise
            $scopedVarName = (
                $scope->getScopedVariable($syntax->base->identifier)
                ?? $scope->asUnregisteredScopedVariable($syntax->base->identifier)
            );

            return new HindleyVariable($scopedVarName);
        }

        if ($syntax instanceof BlockReturn) {
            // I think it's okay to not use $previousExpression as the rhs of this because if it returns something
            // that itself doesn't use it, then it's all irrelevant anyway
            return new HindleyLet(
                'ret',
                $this->convertToHindleyExpression($scope, $syntax->expression, $previousExpression),
                new HindleyVariable('ret'),
            );
        }

        if ($syntax instanceof SyntaxVariableDefinition) {
            $scope->addUnscopedVariable($syntax->name->identifier);

            return $this->convertToHindleyExpression($scope, $syntax->value, $previousExpression);
        }

        if ($syntax instanceof Addition) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::INT_ADDITION->value),
                    $this->convertToHindleyExpression($scope, $syntax->left, $previousExpression),
                ),
                $this->convertToHindleyExpression($scope, $syntax->right, $previousExpression),
            );
        }

        if ($syntax instanceof Subtraction) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::INT_SUBTRACTION->value),
                    $this->convertToHindleyExpression($scope, $syntax->left, $previousExpression),
                ),
                $this->convertToHindleyExpression($scope, $syntax->right, $previousExpression),
            );
        }

        if ($syntax instanceof FunctionCall) {
            $callee = $syntax->on;

            // just unwrap a group, it'll resolve to one expression anyway that may or may not be valid
            if ($callee instanceof Group) {
                $callee = $callee->operand;
            }

            if ((! ($callee instanceof Variable)) && (! ($callee instanceof FunctionCall))) {
                throw new FailedTypeCheck("Cannot call function on this type");
            }

            $newExpression = match (get_class($callee)) {
                Variable::class => new HindleyVariable($scope->getScopedVariable($callee->base->identifier)),
                FunctionCall::class => $this->convertToHindleyExpression($scope, $callee, $previousExpression),
            };

            // TODO argument count check here
            foreach ($syntax->arguments as $argument) {
                $newExpression = new HindleyApplication(
                    $newExpression,
                    $this->convertToHindleyExpression($scope, $argument, $previousExpression),
                );
            }

            // we need a temporary variable to wrap this with so that we can encompass the result in the previous
            // expression
            $letExprNumber = $this->letExprCounter;
            $this->letExprCounter++;

            if ($previousExpression === null) {
                return $newExpression;
            }

            return new HindleyLet("_let$letExprNumber", $newExpression, $previousExpression);
        }

        throw new FailedTypeCheck(
            "Unhandled syntax for conversion to Hindley-Milner expression type: " . get_class($syntax),
        );
    }
}
