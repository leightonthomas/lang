<?php

declare(strict_types=1);

namespace App\Interpreter;

use App\Compiler\CustomBytecode\ByteReader;
use App\Compiler\CustomBytecode\ProgramCompiler;
use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Model\Interpreter\FunctionDefinition;
use App\Model\Interpreter\StackFrame;
use RuntimeException;

use function array_key_exists;
use function array_key_last;
use function array_pop;
use function array_reverse;

final class CustomBytecodeInterpreter
{
    /** @var list<StackFrame> */
    private array $stack;
    /** @var array<string, FunctionDefinition> indexed by name */
    private array $functions;
    private StackFrame $currentFrame;
    private ByteReader $byteReader;

    public function interpret(string $bytecode): int
    {
        $this->stack = [];
        $this->functions = [];
        $this->byteReader = new ByteReader($bytecode);

        // parse the structure
        while (true) {
            $rawStruct = $this->byteReader->readUnsignedShort();

            $struct = Structure::tryFrom($rawStruct);
            if ($struct === Structure::END) {
                break;
            }

            match ($struct) {
                Structure::FN => $this->addFunction(),
                default => throw new RuntimeException('Unhandled structure opcode: ' . $struct->name),
            };
        }

        /**
         * we need to always have a frame, so create a global one which will be used for handling the return value
         * of the hardcoded main function - {@see ProgramCompiler::compile()}
         */
        $globalFrame = new StackFrame('', $this->byteReader->pointer);
        $this->stack[] = $globalFrame;
        $this->currentFrame = $globalFrame;

        // now we can execute the actual code
        while (true) {
            $rawOpcode = $this->byteReader->readUnsignedShort();

            $opcode = Opcode::tryFrom($rawOpcode);
            if ($opcode === Opcode::END) {
                return $this->currentFrame->pop();
            }

            match ($opcode) {
                Opcode::PUSH => $this->push(),
                Opcode::LET => $this->let(),
                Opcode::LOAD => $this->load(),
                Opcode::CALL => $this->call(),
                Opcode::RET => $this->ret(),
                Opcode::SUB => $this->sub(),
                Opcode::ADD => $this->add(),
                Opcode::NEG => $this->neg(),
                Opcode::ECHO => $this->echo(),
                null => throw new RuntimeException('Unhandled opcode: ' . $rawOpcode),
                default => throw new RuntimeException('Unhandled opcode: ' . $opcode->name),
            };
        }
    }

    private function ret(): void
    {
        $returnValue = $this->currentFrame->pop();
        $this->byteReader->pointer = $this->currentFrame->returnPointer;

        array_pop($this->stack);

        $lastFrameKey = array_key_last($this->stack);
        if (array_key_exists($lastFrameKey, $this->stack)) {
            $previousFrame = $this->stack[$lastFrameKey];

            $this->currentFrame = $previousFrame;
            $this->currentFrame->push($returnValue);
        }
    }

    private function echo(): void
    {
        echo $this->currentFrame->get();
    }

    private function sub(): void
    {
        $right = $this->currentFrame->pop();
        $left = $this->currentFrame->pop();

        $this->currentFrame->push($left - $right);
    }

    private function neg(): void
    {
        $operand = $this->currentFrame->pop();

        $this->currentFrame->push($operand * -1);
    }

    private function add(): void
    {
        $right = $this->currentFrame->pop();
        $left = $this->currentFrame->pop();

        $this->currentFrame->push($left + $right);
    }

    private function let(): void
    {
        $this->currentFrame->setNamedValue($this->byteReader->readString(), $this->currentFrame->pop());
    }

    private function load(): void
    {
        $this->currentFrame->push($this->currentFrame->getNamedValue($this->byteReader->readString()));
    }

    private function push(): void
    {
        $value = $this->byteReader->readUnsignedLongLong();

        $this->currentFrame->push($value);
    }

    private function call(): void
    {
        $functionName = $this->byteReader->readString();
        $returnPointer = $this->byteReader->pointer;

        $definition = $this->functions[$functionName];

        $this->byteReader->pointer = $definition->offset;

        $oldFrame = $this->currentFrame;
        $stackFrame = new StackFrame($functionName, $returnPointer);

        $this->stack[] = $stackFrame;
        $this->currentFrame = $stackFrame;

        foreach (array_reverse($definition->arguments) as $argumentName) {
            $this->currentFrame->setNamedValue($argumentName, $oldFrame->pop());
        }
    }

    private function addFunction(): void
    {
        $fn = $this->byteReader->readFunctionDefinition();

        $this->functions[$fn->name] = $fn;
    }
}
