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
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable as TypeVariable;
use App\Model\StandardType;
use App\Model\Syntax\Expression;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Boolean;
use App\Model\Syntax\Simple\CodeBlock;
use App\Model\Syntax\Simple\Definition\VariableDefinition;
use App\Model\Syntax\Simple\IfStatement;
use App\Model\Syntax\Simple\Infix\Addition;
use App\Model\Syntax\Simple\Infix\FunctionCall;
use App\Model\Syntax\Simple\Infix\GreaterThan;
use App\Model\Syntax\Simple\Infix\GreaterThanEqual;
use App\Model\Syntax\Simple\Infix\IsEqual;
use App\Model\Syntax\Simple\Infix\LessThan;
use App\Model\Syntax\Simple\Infix\LessThanEqual;
use App\Model\Syntax\Simple\Infix\Subtraction;
use App\Model\Syntax\Simple\IntegerLiteral as SyntaxIntegerLiteral;
use App\Model\Syntax\Simple\Prefix\Group;
use App\Model\Syntax\Simple\Prefix\Minus;
use App\Model\Syntax\Simple\Prefix\Not;
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
    private int $transientCounter;
    /** @var WeakMap<Expression, Monotype> */
    private WeakMap $inferredTypes;
    private Context $context;

    public function __construct(
        private readonly TypeInferer $typeInferer,
    ) {
        $this->transientCounter = 0;
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
        $this->inferredTypes = new WeakMap();
        $this->context = new Context([
            StandardType::STRING->value => new TypeApplication(StandardType::STRING->value, []),
            StandardType::INT->value => new TypeApplication(StandardType::INT->value, []),
            StandardType::BOOL->value => new TypeApplication(StandardType::BOOL->value, []),
            'true' => new TypeApplication(StandardType::BOOL->value, []),
            'false' => new TypeApplication(StandardType::BOOL->value, []),
            StandardType::UNIT->value => new TypeApplication(StandardType::UNIT->value, []),
            StandardType::BOOL_NEGATION->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::BOOL->value, []),
                    new TypeApplication(StandardType::BOOL->value, []),
                ],
            ),
            StandardType::INT_NEGATION->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(StandardType::INT->value, []),
                ],
            ),
            StandardType::INT_GREATER_THAN->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeApplication(StandardType::INT->value, []),
                            new TypeApplication(StandardType::BOOL->value, []),
                        ],
                    ),
                ],
            ),
            StandardType::INT_GREATER_THAN_EQ->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeApplication(StandardType::INT->value, []),
                            new TypeApplication(StandardType::BOOL->value, []),
                        ],
                    ),
                ],
            ),
            StandardType::INT_LESS_THAN->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeApplication(StandardType::INT->value, []),
                            new TypeApplication(StandardType::BOOL->value, []),
                        ],
                    ),
                ],
            ),
            StandardType::INT_LESS_THAN_EQ->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeApplication(StandardType::INT->value, []),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeApplication(StandardType::INT->value, []),
                            new TypeApplication(StandardType::BOOL->value, []),
                        ],
                    ),
                ],
            ),
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
            // two quantifiers so that either side can be any value
            StandardType::EQUALITY->value => new Quantifier(
                'l',
                new Quantifier(
                    'r',
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeVariable('l'),
                            new TypeApplication(
                                StandardType::FUNCTION_APPLICATION,
                                [
                                    new TypeVariable('r'),
                                    new TypeApplication(StandardType::BOOL->value, []),
                                ],
                            ),
                        ],
                    ),
                ),
            ),
        ]);

        $globalScope = new Scope('');
        $globalScope->addUnscopedVariable(StandardType::UNIT->value);

        /**
         * functions require a type to be set up-front, so we can add that to the global context
         *
         * @var class-string<StandardFunction> $standardFunction
         */
        foreach (StandardFunction::FUNCTIONS as $standardFunction) {
            // start off with just the return type of the function, that way we can build on it for each arg
            // and wrap it in an application, or if it has 0 arguments it's valid as just an alias to the return type
            $fnExpression = $this->context->attemptTypeResolution($standardFunction::getReturnType());
            foreach (array_reverse($standardFunction::getArguments()) as $type) {
                $fnExpression = new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [$this->context->attemptTypeResolution($type->value), $fnExpression],
                );
            }

            $this->context[$standardFunction::getName()] = $fnExpression;
            $globalScope->addUnscopedVariable($standardFunction::getName());
        }

        foreach ($parsedOutput->functions as $function) {
            $fnExpression = $this->context->attemptTypeResolution($function->assignedType->base->identifier);
            foreach (array_reverse($function->arguments) as ['type' => $type]) {
                $fnExpression = new TypeApplication(
                    StandardType::FUNCTION_APPLICATION,
                    [
                        $this->context->attemptTypeResolution($type->base->identifier),
                        $fnExpression,
                    ],
                );
            }

            $this->context[$function->name->identifier] = $fnExpression;
            $globalScope->addUnscopedVariable($function->name->identifier);
        }

        foreach ($parsedOutput->functions as $function) {
            $fnScope = $globalScope->makeChildScope($function->name->identifier);
            foreach ($function->arguments as ['name' => $name, 'type' => $type]) {
                $fnScope->addUnscopedVariable($name->identifier);
                $this->context[$fnScope->getScopedVariable($name->identifier)] = $this->context->attemptTypeResolution(
                    $type->base->identifier,
                );
            }

            $this->assignTypesToExpressions($fnScope, $function->codeBlock->expressions);
        }

        return ['types' => $this->inferredTypes, 'context' => $this->context];
    }

    /**
     * @param list<Expression> $expressions
     *
     * @throws FailedTypeCheck
     */
    private function assignTypesToExpressions(Scope $scope, array $expressions): void
    {
        foreach ($expressions as $expression) {
            if ($expression instanceof IfStatement) {
                $this->assignTypesToExpressions($scope, [$expression->condition]);

                $ifConditionScope = $scope->makeChildScope("if{$this->getTransient()}");
                $this->assignTypesToExpressions($ifConditionScope, $expression->then->expressions);

                // we don't expect if statements to ever have their own type, so we don't need to do code block handling
                // in here

                continue;
            }

            // if the variable has a code block as its value we need to resolve the return type of the block rather
            // than directly convert its value to a HM expression
            if (($expression instanceof VariableDefinition) && ($expression->value instanceof CodeBlock)) {
                $codeBlockType = $this->assignTypesToCodeBlocks($expression->value, $scope);

                $scope->addUnscopedVariable($expression->name->identifier);
                $scopedVarName = $scope->getScopedVariable($expression->name->identifier);
                $this->context[$scopedVarName] = $codeBlockType;
                $this->inferredTypes[$expression] = $codeBlockType;

                continue;
            }

            // similarly with block return, handle it separately if it has a code block value
            if (($expression instanceof BlockReturn) && ($expression->expression instanceof CodeBlock)) {
                $codeBlockType = $this->assignTypesToCodeBlocks($expression->expression, $scope);

                $this->inferredTypes[$expression] = $codeBlockType;

                continue;
            }

            if ($expression instanceof CodeBlock) {
                $this->assignTypesToCodeBlocks($expression, $scope);

                continue;
            }

            $hindleyExpression = $this->convertToHindleyExpression($scope, $expression);

            try {
                $inferenceResult = $this->typeInferer->infer($this->context, $hindleyExpression);

                $this->inferredTypes[$expression] = $inferenceResult[1];
            } catch (FailedToInferType $e) {
                throw new FailedTypeCheck("Failed to infer types", 0, $e);
            }

            if ($expression instanceof VariableDefinition) {
                $scopedVarName = $scope->getScopedVariable($expression->name->identifier);
                $actualVarType = $this->inferredTypes[$expression] ?? null;
                if ($actualVarType === null) {
                    throw new FailedTypeCheck("Expected to have gotten a type for this variable by now");
                }

                $this->context[$scopedVarName] = $actualVarType;
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

        if ($syntax instanceof Boolean) {
            return new HindleyVariable(StandardType::BOOL->value);
        }

        // since we don't care about runtime-level types we can just give the same type as the operand, since it'll
        // never change based on it having a prefix (even true & false resolve to bool)
        // some specific ones need manual checking though (e.g. not)
        if ($syntax instanceof Prefix) {
            if ($syntax instanceof Not) {
                return new HindleyApplication(
                    new HindleyVariable(StandardType::BOOL_NEGATION->value),
                    $this->convertToHindleyExpression($scope, $syntax->operand, $previousExpression),
                );
            }

            if ($syntax instanceof Minus) {
                return new HindleyApplication(
                    new HindleyVariable(StandardType::INT_NEGATION->value),
                    $this->convertToHindleyExpression($scope, $syntax->operand, $previousExpression),
                );
            }

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
            $returnExpr = $syntax->expression;
            if ($returnExpr === null) {
                return new HindleyVariable(StandardType::UNIT->value);
            }

            // I think it's okay to not use $previousExpression as the rhs of this because if it returns something
            // that itself doesn't use it, then it's all irrelevant anyway
            return new HindleyLet(
                'ret',
                $this->convertToHindleyExpression($scope, $returnExpr, $previousExpression),
                new HindleyVariable('ret'),
            );
        }

        if ($syntax instanceof VariableDefinition) {
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

        if ($syntax instanceof LessThan) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::INT_LESS_THAN->value),
                    $this->convertToHindleyExpression($scope, $syntax->left, $previousExpression),
                ),
                $this->convertToHindleyExpression($scope, $syntax->right, $previousExpression),
            );
        }

        if ($syntax instanceof LessThanEqual) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::INT_LESS_THAN_EQ->value),
                    $this->convertToHindleyExpression($scope, $syntax->left, $previousExpression),
                ),
                $this->convertToHindleyExpression($scope, $syntax->right, $previousExpression),
            );
        }

        if ($syntax instanceof GreaterThan) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::INT_GREATER_THAN->value),
                    $this->convertToHindleyExpression($scope, $syntax->left, $previousExpression),
                ),
                $this->convertToHindleyExpression($scope, $syntax->right, $previousExpression),
            );
        }

        if ($syntax instanceof GreaterThanEqual) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::INT_GREATER_THAN_EQ->value),
                    $this->convertToHindleyExpression($scope, $syntax->left, $previousExpression),
                ),
                $this->convertToHindleyExpression($scope, $syntax->right, $previousExpression),
            );
        }

        if ($syntax instanceof IsEqual) {
            return new HindleyApplication(
                new HindleyApplication(
                    new HindleyVariable(StandardType::EQUALITY->value),
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

            foreach ($syntax->arguments as $argument) {
                $newExpression = new HindleyApplication(
                    $newExpression,
                    $this->convertToHindleyExpression($scope, $argument, $previousExpression),
                );
            }

            if ($previousExpression === null) {
                return $newExpression;
            }

            // we need a temporary variable to wrap this with so that we can encompass the result in the previous
            // expression
            $letExprNumber = $this->getTransient();

            return new HindleyLet("_let$letExprNumber", $newExpression, $previousExpression);
        }

        throw new FailedTypeCheck(
            "Unhandled syntax for conversion to Hindley-Milner expression type: " . get_class($syntax),
        );
    }

    /**
     * @throws FailedTypeCheck
     */
    private function assignTypesToCodeBlocks(CodeBlock $block, Scope $scope): Polytype
    {
        $codeBlockScope = $scope->makeChildScope("codeBlock{$this->getTransient()}");

        $this->assignTypesToExpressions($codeBlockScope, $block->expressions);

        $codeBlockReturnStatement = $block->getFirstReturnStatement();
        if ($codeBlockReturnStatement === null) {
            $codeBlockReturnType = $this->context->attemptTypeResolution(StandardType::UNIT);
        } else {
            $codeBlockReturnType = (
                $this->inferredTypes[$codeBlockReturnStatement]
                ?? $this->context->attemptTypeResolution(StandardType::UNIT)
            );
        }

        $this->inferredTypes[$block] = $codeBlockReturnType;

        return $codeBlockReturnType;
    }

    private function getTransient(): int
    {
        $value = $this->transientCounter;
        $value++;

        return $value;
    }
}
