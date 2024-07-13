<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Compiler\CustomBytecode\JumpMode;
use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Boolean;
use App\Model\Syntax\Simple\CodeBlock;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use App\Model\Syntax\Simple\Definition\VariableDefinition;
use App\Model\Syntax\Simple\IfStatement;
use App\Model\Syntax\Simple\Infix\BinaryInfix;
use App\Model\Syntax\Simple\Infix\FunctionCall;
use App\Model\Syntax\Simple\IntegerLiteral;
use App\Model\Syntax\Simple\Prefix\Group;
use App\Model\Syntax\Simple\Prefix\Minus;
use App\Model\Syntax\Simple\Prefix\Not;
use App\Model\Syntax\Simple\StringLiteral;
use App\Model\Syntax\Simple\Variable;
use App\Model\Syntax\Simple\VariableReassignment;
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

        $this->writeCodeBlock($definition->codeBlock, startFrame: false, forceReturn: true);

        return $this->instructions->finish();
    }

    /**
     * @param bool $startFrame whether to start a frame for the code-block
     * @param bool $forceReturn ensure there's always a return value by adding PUSH_UNIT & RET if no actual return found
     */
    private function writeCodeBlock(CodeBlock $block, bool $startFrame, bool $forceReturn): void
    {
        if ($startFrame) {
            $this->instructions->write(pack("S", Opcode::START_FRAME->value));
        }

        $hadReturnStatement = false;
        foreach ($block->expressions as $expression) {
            if ($expression instanceof BlockReturn) {
                $hadReturnStatement = true;
            }

            // an "orphaned" code block, just used for scoping
            if ($expression instanceof CodeBlock) {
                $this->writeCodeBlock($expression, startFrame: true, forceReturn: true);

                continue;
            }

            if ($expression instanceof VariableDefinition) {
                $varName = $expression->name->identifier;
                $varValue = $expression->value;

                if ($varValue instanceof CodeBlock) {
                    $this->writeCodeBlock($varValue, startFrame: true, forceReturn: true);
                } else {
                    $this->writeSubExpression($varValue);
                }

                $this->instructions->write(pack("SQH*", Opcode::LET->value, mb_strlen($varName), bin2hex($varName)));

                continue;
            }

            if ($expression instanceof VariableReassignment) {
                $varName = $expression->variable->identifier;
                $varValue = $expression->newValue;

                if ($varValue instanceof CodeBlock) {
                    $this->writeCodeBlock($varValue, startFrame: true, forceReturn: true);
                } else {
                    $this->writeSubExpression($varValue);
                }

                $this->instructions->write(pack("SQH*", Opcode::LET->value, mb_strlen($varName), bin2hex($varName)));

                continue;
            }

            if ($expression instanceof IfStatement) {
                $hadReturnStatement = $hadReturnStatement || ($expression->then->getFirstReturnStatement() !== null);

                $this->instructions->startGroup();

                $this->writeCodeBlock($expression->then, startFrame: false, forceReturn: false);

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

                continue;
            }

            match (true) {
                ($expression instanceof SubExpression) => $this->writeSubExpression($expression),
                ($expression instanceof BlockReturn) => $this->writeSubExpression($expression),
                default => throw new RuntimeException("Unhandled expression: " . get_class($expression)),
            };

            if ($expression instanceof BlockReturn) {
                return;
            }
        }

        if ($forceReturn && (! $hadReturnStatement)) {
            $this->instructions->write(pack("SS", Opcode::PUSH_UNIT->value, Opcode::RET->value));
        }
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
            if ($returnExpr instanceof CodeBlock) {
                $this->writeCodeBlock($returnExpr, startFrame: true, forceReturn: true);
            } elseif ($returnExpr !== null) {
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

        if ($expression instanceof BinaryInfix) {
            $this->writeSubExpression($expression->left);
            $this->writeSubExpression($expression->right);

            $this->instructions->write(pack("S", $expression::getOpcode()->value));

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
