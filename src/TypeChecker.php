<?php

declare(strict_types=1);

namespace App;

use App\Inference\TypeInferer;
use App\Model\Compiler\CustomBytecode\Standard\Function\FnEcho;
use App\Model\Exception\Inference\FailedToInferType;
use App\Model\Exception\TypeChecker\FailedTypeCheck;
use App\Model\Inference\Context;
use App\Model\Inference\Expression\Application as HindleyApplication;
use App\Model\Inference\Expression\Expression as HindleyExpression;
use App\Model\Inference\Expression\Let as HindleyLet;
use App\Model\Inference\Expression\Variable as HindleyVariable;
use App\Model\Inference\Type\Application as TypeApplication;
use App\Model\Inference\Type\Quantifier as TypeQuantifier;
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
use function count;
use function get_class;
use function json_encode;
use function random_bytes;
use function sprintf;
use function var_dump;

use const JSON_PRETTY_PRINT;

final readonly class TypeChecker
{
    public function __construct(
        private TypeInferer $typeInferer,
    ) {
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
            FnEcho::getName() => new TypeQuantifier(
                't',
                new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        new TypeVariable('t'),
                        new TypeApplication(StandardType::UNIT->value, []),
                    ],
                ),
            ),
        ]);

        $globalScope = new Scope('');
        $globalScope->addUnscopedVariable(FnEcho::getName());

        // functions require a type to be set up-front, so we can add that to the global context
        foreach ($parsedOutput->functions as $function) {
            $context[$function->name->identifier] = new TypeVariable($function->assignedType->base->identifier);

            $globalScope->addUnscopedVariable($function->name->identifier);
        }

        foreach ($parsedOutput->functions as $function) {
            $fnScope = $globalScope->makeChildScope($function->name->identifier);

            /** @var HindleyExpression|null $hindleyExpression */
            $hindleyExpression = null;

            // we have to reverse this so that the let expressions work as intended
            foreach (array_reverse($function->codeBlock->expressions) as $expression) {
                $hindleyExpression = $this->convertToHindleyExpression(
                    $fnScope,
                    $expression,
                    $hindleyExpression,
                );
            }

            if ($hindleyExpression === null) {
                throw new FailedTypeCheck("Received a function that had no expressions inside, could not resolve type");
            }

            try {
                $inferenceResult = $this->typeInferer->infer($context, $hindleyExpression);

                $actualFunctionType = $inferenceResult[1];
            } catch (FailedToInferType $e) {
                throw new FailedTypeCheck("Failed to infer types", 0, $e);
            }

            $actualType = match (get_class($actualFunctionType)) {
                TypeApplication::class => $actualFunctionType->constructor,
                TypeVariable::class => $actualFunctionType->name,
            };

            if ($actualType !== $function->assignedType->base->identifier) {
                var_dump(json_encode($inferenceResult[0], JSON_PRETTY_PRINT));

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

    /**
     * @param list<string> $varNameParts
     *
     * @throws FailedTypeCheck
     */
    private function convertToHindleyExpression(
        Scope $scope,
        SimpleSyntax $syntax,
        ?HindleyExpression $previousExpression,
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

            return new HindleyLet(
                $scope->getScopedVariable($syntax->name->identifier),
                $this->convertToHindleyExpression($scope, $syntax->value, $previousExpression),
                $previousExpression,
            );
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

            // basically just unwrap a group, it'll resolve to one expression anyway that may or may not be valid
            if ($callee instanceof Group) {
                $callee = $callee->operand;
            }

            if ((! ($callee instanceof Variable)) && (! ($callee instanceof FunctionCall))) {
                throw new FailedTypeCheck("Cannot call function on this type");
            }

            if (count($syntax->arguments) <= 0) {
                return match (get_class($callee)) {
                    Variable::class => new HindleyVariable(
                        $scope->getScopedVariable($callee->base->identifier)
                        ?? $scope->asUnregisteredScopedVariable($callee->base->identifier)
                    ),
                    FunctionCall::class => $this->convertToHindleyExpression($scope, $callee, $previousExpression),
                    default => throw new FailedTypeCheck("Cannot call function on this type"),
                };
            }

            $newExpression = match (get_class($callee)) {
                Variable::class => new HindleyVariable($scope->getScopedVariable($callee->base->identifier)),
                FunctionCall::class => $this->convertToHindleyExpression($scope, $callee, $previousExpression),
            };

            foreach ($syntax->arguments as $argument) {
                $newExpression = new HindleyApplication(
                    $newExpression,
                    $this->convertToHindleyExpression($scope, $argument, $previousExpression),
                );
            }

            // we need a temporary variable to wrap this with so that we can encompass the result in the previous
            // expression - this is relatively expensive though and should probably be replaced with a counter
            $letExprVariable = random_bytes(32);

            return new HindleyLet($letExprVariable, $newExpression, $previousExpression);
        }

        throw new FailedTypeCheck(
            "Unhandled syntax for conversion to Hindley-Milner expression type: " . get_class($syntax),
        );
    }
}
