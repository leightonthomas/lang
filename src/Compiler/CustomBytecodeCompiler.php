<?php

declare(strict_types=1);

namespace App\Compiler;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Syntax\Simple\BlockReturn;
use App\Model\Syntax\Simple\Definition\VariableDefinition;
use App\Model\Syntax\Simple\IntegerLiteral;
use App\Model\Syntax\Simple\Variable;
use App\Model\Syntax\SubExpression;
use App\Parser\ParsedOutput;
use RuntimeException;

use function bin2hex;
use function get_class;
use function intval;
use function join;
use function mb_strlen;
use function pack;

final class CustomBytecodeCompiler
{
    /** @var list<string> */
    private array $instructions = [];

    public function compile(ParsedOutput $parsed): string
    {
        // reset
        $this->instructions = [];

        $mainFn = $parsed->functions['main'] ?? null;
        if ($mainFn === null) {
            // TODO temporary
            throw new RuntimeException("no main function");
        }

        foreach ($mainFn->codeBlock->expressions as $expression) {
            if ($expression instanceof BlockReturn) {
                $this->writeSubExpression($expression->expression);
                $this->writeEnd();

                continue;
            }

            if ($expression instanceof VariableDefinition) {
                $varName = $expression->name->identifier;

                $this->writeSubExpression($expression->value);
                $this->instructions[] = pack("SPH*", Opcode::LET->value, mb_strlen($varName), bin2hex($varName));

                continue;
            }

            if ($expression instanceof SubExpression) {
                $this->writeSubExpression($expression);

                continue;
            }

            throw new RuntimeException("Unhandled expression: " . get_class($expression));
        }

        return join('', $this->instructions);
    }

    private function writeSubExpression(SubExpression $expression): void
    {
        if ($expression instanceof IntegerLiteral) {
            $this->instructions[] = pack("SP", Opcode::PUSH->value, intval($expression->base->integer));

            return;
        }

        if ($expression instanceof Variable) {
            $varName = $expression->base->identifier;

            $this->instructions[] = pack("SPH*", Opcode::LOAD->value, mb_strlen($varName), bin2hex($varName));

            return;
        }

        throw new RuntimeException("Unhandled subexpression: " . get_class($expression));
    }

    private function writeEnd(): void
    {
        $this->instructions[] = pack("S", Opcode::END->value);
    }

    private function writeVariableDeclaration(): void
    {

    }
}
