<?php

declare(strict_types=1);

namespace App\Checking;

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
use App\Model\Inference\Type\Monotype;
use App\Model\StandardType;
use App\Model\Syntax\Expression;
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
use WeakMap;

use function array_reverse;
use function get_class;

/**
 * Infers types for all relevant expressions, for use in other checkers
 */
final class InferenceChecker
{
    private int $letExprCounter;

    public function __construct(
        private readonly TypeInferer $typeInferer,
    ) {
        $this->letExprCounter = 0;
    }

    /**
     * @param ParsedOutput $parsedOutput
     *
     * @return array{types: WeakMap<Expression, Monotype>, context: Context}
     *
     * @throws FailedTypeCheck
     */
    public function check(ParsedOutput $parsedOutput): array
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

        /** @var WeakMap<Expression, Monotype> $inferredTypes */
        $inferredTypes = new WeakMap();
        $globalScope = new Scope('');

        /**
         * functions require a type to be set up-front, so we can add that to the global context
         *
         * @var class-string<StandardFunction> $standardFunction
         */
        foreach (StandardFunction::FUNCTIONS as $standardFunction) {
            // start off with just the return type of the function, that way we can build on it for each arg
            // and wrap it in an application, or if it has 0 arguments it's valid as just an alias to the return type
            $fnExpression = $context->attemptTypeResolution($standardFunction::getReturnType());
            foreach (array_reverse($standardFunction::getArguments()) as $type) {
                $fnExpression = new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [$context->attemptTypeResolution($type->value), $fnExpression],
                );
            }

            $context[$standardFunction::getName()] = $fnExpression;
            $globalScope->addUnscopedVariable($standardFunction::getName());
        }

        foreach ($parsedOutput->functions as $function) {
            $fnExpression = $context->attemptTypeResolution($function->assignedType->base->identifier);
            foreach (array_reverse($function->arguments) as ['type' => $type]) {
                $fnExpression = new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        $context->attemptTypeResolution($type->base->identifier),
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
                $context[$fnScope->getScopedVariable($name->identifier)] = $context->attemptTypeResolution(
                    $type->base->identifier,
                );
            }

            foreach ($function->codeBlock->expressions as $expression) {
                $hindleyExpression = $this->convertToHindleyExpression($fnScope, $expression);

                try {
                    $inferenceResult = $this->typeInferer->infer($context, $hindleyExpression);

                    $inferredTypes[$expression] = $inferenceResult[1];
                } catch (FailedToInferType $e) {
                    throw new FailedTypeCheck("Failed to infer types", 0, $e);
                }

                if ($expression instanceof SyntaxVariableDefinition) {
                    $scopedVarName = $fnScope->getScopedVariable($expression->name->identifier);
                    $actualVarType = $inferredTypes[$expression] ?? null;
                    if ($actualVarType === null) {
                        throw new FailedTypeCheck("Expected to have gotten a type for this variable by now");
                    }

                    $context[$scopedVarName] = $actualVarType;
                }
            }
        }

        return ['types' => $inferredTypes, 'context' => $context];
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
