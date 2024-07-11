<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Compiler\CustomBytecode\JumpMode;
use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Boolean;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use App\Model\Syntax\Simple\Definition\VariableDefinition;
use App\Model\Syntax\Simple\IfStatement;
use App\Model\Syntax\Simple\Infix\Addition;
use App\Model\Syntax\Simple\Infix\FunctionCall;
use App\Model\Syntax\Simple\Infix\Subtraction;
use App\Model\Syntax\Simple\IntegerLiteral;
use App\Model\Syntax\Simple\Prefix\Group;
use App\Model\Syntax\Simple\Prefix\Minus;
use App\Model\Syntax\Simple\Prefix\Not;
use App\Model\Syntax\Simple\StringLiteral;
use App\Model\Syntax\Simple\Variable;
use App\Model\Syntax\SubExpression;
use RuntimeException;

use function bin2hex;
use function get_class;
use function intval;
use function join;
use function mb_strlen;
use function pack;

final class FunctionCompiler
{
    private InstructionWriter $instructions;

    public function __construct()
    {
        $this->instructions = new InstructionWriter();
    }

    public function compile(FunctionDefinition $definition): string
    {
        $this->instructions = new InstructionWriter();

        $hadReturnStatement = false;
        foreach ($definition->codeBlock->expressions as $expression) {
            if ($expression instanceof BlockReturn) {
                $hadReturnStatement = true;
            }

            if ($expression instanceof VariableDefinition) {
                $varName = $expression->name->identifier;

                $this->writeSubExpression($expression->value);
                $this->instructions->write(pack("SQH*", Opcode::LET->value, mb_strlen($varName), bin2hex($varName)));

                continue;
            }

            if (($expression instanceof SubExpression) || ($expression instanceof BlockReturn)) {
                $this->writeSubExpression($expression);

                continue;
            }

            throw new RuntimeException("Unhandled expression: " . get_class($expression));
        }

        if (! $hadReturnStatement) {
            $this->instructions->write(pack("SS", Opcode::PUSH_UNIT->value, Opcode::RET->value));
        }

        return $this->instructions->finish();
    }

    private function writeSubExpression(SubExpression|BlockReturn $expression): void
    {
        if ($expression instanceof IntegerLiteral) {
            $this->instructions->write(pack("SQ", Opcode::PUSH_INT->value, intval($expression->base->integer)));

            return;
        }

        if ($expression instanceof StringLiteral) {
            $literal = $expression->base->content;

            $this->instructions->write(pack("SQH*", Opcode::PUSH_STRING->value,  mb_strlen($literal), bin2hex($literal)));

            return;
        }

        if ($expression instanceof Boolean) {
            $this->instructions->write(pack("SS", Opcode::PUSH_BOOL->value, (int) $expression->value));

            return;
        }

        if ($expression instanceof BlockReturn) {
            $returnExpr = $expression->expression;
            if ($returnExpr !== null) {
                $this->writeSubExpression($expression->expression);
            } else {
                $this->instructions->write(pack("S", Opcode::PUSH_UNIT->value));
            }

            $this->instructions->write(pack("S", Opcode::RET->value));

            return;
        }

        if ($expression instanceof Variable) {
            $varName = $expression->base->identifier;

            $this->instructions->write(pack("SQH*", Opcode::LOAD->value, mb_strlen($varName), bin2hex($varName)));

            return;
        }

        if ($expression instanceof Group) {
            $this->writeSubExpression($expression->operand);

            return;
        }

        if ($expression instanceof Subtraction) {
            $this->writeSubExpression($expression->left);
            $this->writeSubExpression($expression->right);

            $this->instructions->write(pack("S", Opcode::SUB->value));

            return;
        }

        if ($expression instanceof Addition) {
            $this->writeSubExpression($expression->left);
            $this->writeSubExpression($expression->right);

            $this->instructions->write(pack("S", Opcode::ADD->value));

            return;
        }

        if ($expression instanceof Minus) {
            $this->writeSubExpression($expression->operand);

            $this->instructions->write(pack("S", Opcode::NEGATE_INT->value));

            return;
        }

        if ($expression instanceof Not) {
            $this->writeSubExpression($expression->operand);

            $this->instructions->write(pack("S", Opcode::NEGATE_BOOL->value));

            return;
        }

        if ($expression instanceof IfStatement) {
            $this->instructions->startGroup();

            foreach ($expression->then->expressions as $thenExpression) {
                $this->writeSubExpression($thenExpression);
            }

            $instructionsInThenBody = join('', $this->instructions->endGroup());

            // write the condition, then jump mode, then actual jump command
            $this->writeSubExpression($expression->condition);
            $this->instructions->write(pack("SQ", Opcode::PUSH_INT->value, JumpMode::IF_FALSE->value));
            $this->instructions->write(pack(
                "SQ",
                Opcode::JUMP->value,
                // this needs to be the number of BYTES to jump, not number of packed instructions
                mb_strlen($instructionsInThenBody),
            ));

            // then append the instructions that we'd jump over if condition not met
            $this->instructions->write($instructionsInThenBody);

            return;
        }

        if ($expression instanceof FunctionCall) {
            $on = $expression->on;
            if (! ($on instanceof Variable)) {
                throw new RuntimeException('only calling on vars supported atm');
            }

            foreach ($expression->arguments as $arg) {
                $this->writeSubExpression($arg);
            }

            $varName = $on->base->identifier;

            $this->instructions->write(pack("SQH*", Opcode::CALL->value, mb_strlen($varName), bin2hex($varName)));

            return;
        }

        throw new RuntimeException("Unhandled subexpression: " . get_class($expression));
    }
}
