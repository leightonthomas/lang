<?php

declare(strict_types=1);

namespace App\Checking;

use App\Compiler\Program;
use App\Model\Exception\TypeChecker\FailedTypeCheck;
use App\Model\Syntax\Simple\CodeBlock;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use App\Model\Syntax\Simple\Definition\VariableDefinition;
use App\Model\Syntax\Simple\IfStatement;
use App\Model\Syntax\Simple\Infix\BinaryInfix;
use App\Model\Syntax\Simple\Infix\FunctionCall;
use App\Model\Syntax\Simple\Prefix\Prefix;
use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\Simple\Variable;
use RuntimeException;

use function count;

class FunctionCallArgumentCountChecker
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
                $this->checkSyntax($expression, $program);
            }
        }
    }

    private function checkSyntax(SimpleSyntax $syntax, Program $program): void
    {
        if ($syntax instanceof VariableDefinition) {
            $this->checkSyntax($syntax->value, $program);

            return;
        }

        if ($syntax instanceof IfStatement) {
            $this->checkSyntax($syntax->condition, $program);
            $this->checkSyntax($syntax->then, $program);

            return;
        }

        if ($syntax instanceof CodeBlock) {
            foreach ($syntax->expressions as $expression) {
                $this->checkSyntax($expression, $program);
            }

            return;
        }

        if ($syntax instanceof BinaryInfix) {
            $this->checkSyntax($syntax->left, $program);
            $this->checkSyntax($syntax->right, $program);

            return;
        }

        if ($syntax instanceof Prefix) {
            $this->checkSyntax($syntax->operand, $program);

            return;
        }

        if (! ($syntax instanceof FunctionCall)) {
            return;
        }

        $callee = $syntax->on;
        if (! ($callee instanceof Variable)) {
            throw new RuntimeException("Calling functions not supported on non-identifiers");
        }

        $functionBeingCalled = $program->getFunction($callee->base->identifier);

        if (count($syntax->arguments) !== count($functionBeingCalled->arguments)) {
            throw new RuntimeException("Wrong number of arguments to '$functionBeingCalled->name'");
        }
    }
}
