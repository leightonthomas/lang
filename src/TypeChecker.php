<?php

declare(strict_types=1);

namespace App;

use App\Inference\TypeInferer;
use App\Model\Exception\Inference\FailedToInferType;
use App\Model\Exception\TypeChecker\FailedTypeCheck;
use App\Model\Inference\Context;
use App\Model\Inference\Expression\Application as HindleyApplication;
use App\Model\Inference\Expression\Expression as HindleyExpression;
use App\Model\Inference\Expression\Let as HindleyLet;
use App\Model\Inference\Expression\Variable as HindleyVariable;
use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Application as TypeApplication;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Variable as TypeVariable;
use App\Model\StandardType;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Definition\VariableDefinition as SyntaxVariableDefinition;
use App\Model\Syntax\Simple\Infix\Addition;
use App\Model\Syntax\Simple\Infix\Subtraction;
use App\Model\Syntax\Simple\IntegerLiteral as SyntaxIntegerLiteral;
use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\Simple\StringLiteral as SyntaxStringLiteral;
use App\Model\Syntax\Simple\Variable as SyntaxVariable;
use App\Model\TypeChecker\Scope;
use App\Parser\ParsedOutput;

use function array_reverse;
use function get_class;
use function sprintf;

final class TypeChecker
{
    public function __construct(
        private readonly TypeInferer $typeInferer,
    ) {
    }

    /**
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
                    new TypeVariable(StandardType::INT->value),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeVariable(StandardType::INT->value),
                            new TypeVariable(StandardType::INT->value),
                        ],
                    ),
                ],
            ),
            StandardType::INT_SUBTRACTION->value => new TypeApplication(
                StandardType::FUNCTION_APPLICATION,
                [
                    new TypeVariable(StandardType::INT->value),
                    new TypeApplication(
                        StandardType::FUNCTION_APPLICATION,
                        [
                            new TypeVariable(StandardType::INT->value),
                            new TypeVariable(StandardType::INT->value),
                        ],
                    ),
                ],
            ),
        ]);

        // functions require a type to be set up-front, so we can add that to the global context
        foreach ($parsedOutput->functions as $function) {
            // TODO this probs needs to be an application??
            $context[$function->name->identifier] = new TypeVariable($function->assignedType->base->identifier);
        }

        foreach ($parsedOutput->functions as $function) {
            $fnScope = new Scope($function->name->identifier);

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
                /** @var Monotype $actualFunctionType */
                $actualFunctionType = $this->typeInferer->infer($context, $hindleyExpression)[1];
            } catch (FailedToInferType $e) {
                throw new FailedTypeCheck("Failed to infer types", 0, $e);
            }

            if (! ($actualFunctionType instanceof Application)) {
                throw new FailedTypeCheck(
                    sprintf(
                        "Function \"%s\" resolved to a variable rather than an application",
                        $fnScope->getScopedName(),
                    ),
                );
            }

            if ($actualFunctionType->constructor !== $function->assignedType->base->identifier) {
                throw new FailedTypeCheck(
                    sprintf(
                        "Function \"%s\" was expected to have return type \"%s\", found \"%s\"",
                        $fnScope->getScopedName(),
                        $function->assignedType->base->identifier,
                        $actualFunctionType->constructor,
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

        if ($syntax instanceof SyntaxVariable) {
            // see if it already exists in a known scope first, fall back to making a temporary one otherwise
            $scopedVarName = (
                $scope->getScopedVariable($syntax->base->identifier)
                ?? $scope->asUnregisteredScopedVariable($syntax->base->identifier)
            );

            return new HindleyVariable($scopedVarName);
        }

        if ($syntax instanceof BlockReturn) {
            return $this->convertToHindleyExpression($scope, $syntax->expression, $previousExpression);
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

        throw new FailedTypeCheck("Unhandled Hindley-Milner expression type: " . get_class($syntax));
    }
}
